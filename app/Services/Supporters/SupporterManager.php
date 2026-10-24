<?php

declare(strict_types=1);

namespace App\Services\Supporters;

use App\Enums\ConsentChannel;
use App\Enums\ConsentPurpose;
use App\Enums\ConsentStatus;
use App\Enums\ContactChannel;
use App\Enums\ContactOutcome;
use App\Enums\RequestChannel;
use App\Enums\VerificationStatus;
use App\Models\ContactAttempt;
use App\Models\Supporter;
use App\Models\User;
use App\Models\WorkflowTransition;
use App\Services\Audit\AuditLogger;
use App\Services\Consent\ConsentService;
use App\Services\Privacy\ErasureService;
use App\Support\Crypto\PhoneHasher;
use App\Support\Phone\NigerianPhoneNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Corrections, verification, contact reveal, follow-ups and merges. All audited. */
final class SupporterManager
{
    public function __construct(private readonly AuditLogger $audit, private readonly ConsentService $consent) {}

    /**
     * @param  array{full_name?: string, phone?: string, lga_id?: int, ward_id?: int|null, polling_unit_id?: int|null, preferred_language?: string, wants_to_volunteer?: bool}  $changes
     */
    public function correct(Supporter $supporter, array $changes, int $expectedVersion, User $by, string $reason): Supporter
    {
        $this->assertNotErased($supporter);

        return DB::transaction(function () use ($supporter, $changes, $expectedVersion, $reason): Supporter {
            $fresh = Supporter::query()->lockForUpdate()->findOrFail($supporter->id);

            if ($fresh->version !== $expectedVersion) {
                throw new StaleRecord;
            }

            if (isset($changes['phone'])) {
                $e164 = NigerianPhoneNumber::normalise($changes['phone']) ?? throw ValidationException::withMessages(['phone' => __('validation.nigerian_mobile')]);
                if ($e164 !== $fresh->phone() && Supporter::query()->where('phone_hash', PhoneHasher::hash($e164))->whereKeyNot($fresh->id)->exists()) {
                    throw ValidationException::withMessages(['phone' => __('supporters.phone_in_use')]);
                }
                if ($e164 !== $fresh->phone()) {
                    $fresh->setPhone($e164);
                    $fresh->forceFill(['phone_verified_at' => null]);
                    if ($fresh->verification_status === VerificationStatus::PhoneVerified) {
                        $fresh->verification_status = VerificationStatus::Unverified;
                    }
                }
                unset($changes['phone']);
            }

            if (array_key_exists('polling_unit_id', $changes) || array_key_exists('ward_id', $changes) || array_key_exists('lga_id', $changes)) {
                $fresh->polling_unit_id = $changes['polling_unit_id'] ?? null;
                $fresh->ward_id = $changes['polling_unit_id'] ?? null ? null : ($changes['ward_id'] ?? null);
                $fresh->lga_id = $changes['lga_id'] ?? $fresh->lga_id;
                unset($changes['polling_unit_id'], $changes['ward_id'], $changes['lga_id']);
            }

            $fresh->fill($changes);

            if (! $fresh->isDirty()) {
                return $fresh;
            }

            $fresh->version++;
            $fresh->save();
            $this->audit->recordChanges('supporter.corrected', $fresh, ['reason' => $reason]);

            return $fresh->refresh();
        });
    }

    public function verify(Supporter $supporter, User $by): void
    {
        $this->transition($supporter, VerificationStatus::Verified, $by, null);
    }

    public function reject(Supporter $supporter, User $by, string $reason): void
    {
        $this->transition($supporter, VerificationStatus::Rejected, $by, $reason);
    }

    /** Returns the full number for an authorised person and records that it was revealed. */
    public function revealPhone(Supporter $supporter, User $by): ?string
    {
        $this->audit->record('supporter.contact_revealed', $supporter, actor: $by);

        return $supporter->phone();
    }

    public function logContact(Supporter $supporter, User $by, ContactChannel $channel, ContactOutcome $outcome, ?string $notes, ?\DateTimeInterface $followUpAt): ContactAttempt
    {
        $this->assertNotErased($supporter);

        return DB::transaction(function () use ($supporter, $by, $channel, $outcome, $notes, $followUpAt): ContactAttempt {
            $attempt = ContactAttempt::query()->create([
                'uuid' => (string) Str::uuid(),
                'supporter_id' => $supporter->id,
                'user_id' => $by->id,
                'senatorial_district_id' => $supporter->senatorial_district_id,
                'lga_id' => $supporter->lga_id,
                'ward_id' => $supporter->ward_id,
                'polling_unit_id' => $supporter->polling_unit_id,
                'channel' => $channel,
                'outcome' => $outcome,
                'notes_encrypted' => $notes === null || trim($notes) === '' ? null : mb_substr($notes, 0, 2000),
                'follow_up_at' => $followUpAt,
                'occurred_at' => now(),
            ]);

            // Asking not to be contacted is honoured immediately, on every channel.
            if ($outcome === ContactOutcome::DoNotContact && $supporter->phone_hash) {
                $this->consent->withdrawAll($supporter->phone_hash, null, RequestChannel::Field, $supporter, ['contact_attempt' => $attempt->uuid], $by);
            }

            // Contact renews the retention period.
            $supporter->forceFill(['retention_until' => now()->addMonths((int) config('campaign.privacy.retention_months'))->toDateString()])->saveQuietly();
            $this->audit->record('supporter.contact_logged', $supporter, null, ['channel' => $channel->value, 'outcome' => $outcome->value], $by);

            return $attempt;
        });
    }

    /**
     * Merges $duplicate into $primary: consents, contacts and requests move;
     * the duplicate is anonymised. Irreversible, so callers confirm first.
     */
    public function merge(Supporter $primary, Supporter $duplicate, User $by): void
    {
        if ($primary->is($duplicate)) {
            throw ValidationException::withMessages(['merge' => 'A record cannot be merged into itself.']);
        }

        $this->assertNotErased($primary);
        $this->assertNotErased($duplicate);

        DB::transaction(function () use ($primary, $duplicate, $by): void {
            ContactAttempt::query()->where('supporter_id', $duplicate->id)->update(['supporter_id' => $primary->id]);
            DB::table('data_subject_requests')->where('supporter_id', $duplicate->id)->update(['supporter_id' => $primary->id]);

            // Consent records are append-only; carry the duplicate's current decisions over as new records.
            if ($duplicate->phone_hash && $duplicate->phone_hash !== $primary->phone_hash) {
                foreach ($this->consent->summary($duplicate->phone_hash) as $channel => $purposes) {
                    foreach ($purposes as $purpose => $status) {
                        if ($status !== null) {
                            $this->consent->record(
                                (string) $primary->phone_hash,
                                ConsentChannel::from($channel),
                                ConsentPurpose::from($purpose),
                                ConsentStatus::from($status->value),
                                RequestChannel::Staff,
                                $primary,
                                ['merged_from' => $duplicate->reference],
                                $by,
                            );
                        }
                    }
                }
            }

            if ($duplicate->wants_to_volunteer && ! $primary->wants_to_volunteer) {
                $primary->forceFill(['wants_to_volunteer' => true])->save();
            }

            app(ErasureService::class)->anonymise($duplicate, $by, 'merged into '.$primary->reference, suppress: false);
            $this->audit->record('supporter.merged', $primary, null, ['merged_from' => $duplicate->reference], $by);
        });
    }

    private function transition(Supporter $supporter, VerificationStatus $to, User $by, ?string $reason): void
    {
        $this->assertNotErased($supporter);

        DB::transaction(function () use ($supporter, $to, $by, $reason): void {
            $from = $supporter->verification_status;
            $supporter->forceFill([
                'verification_status' => $to,
                'verified_by' => $by->id,
                'verified_at' => now(),
                'rejection_reason' => $to === VerificationStatus::Rejected ? $reason : null,
                'version' => $supporter->version + 1,
            ])->save();
            WorkflowTransition::record($supporter, $from->value, $to->value, $by, $reason);
            $this->audit->record('supporter.'.$to->value, $supporter, null, $reason ? ['reason' => $reason] : [], $by);
        });
    }

    private function assertNotErased(Supporter $supporter): void
    {
        if ($supporter->isErased()) {
            throw ValidationException::withMessages(['supporter' => __('supporters.erased')]);
        }
    }
}
