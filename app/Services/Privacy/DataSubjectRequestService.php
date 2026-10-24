<?php

declare(strict_types=1);

namespace App\Services\Privacy;

use App\Enums\DsrStatus;
use App\Enums\DsrType;
use App\Enums\RequestChannel;
use App\Enums\SuppressionReason;
use App\Jobs\SendTransactionalSms;
use App\Models\ConsentRecord;
use App\Models\DataSubjectRequest;
use App\Models\Supporter;
use App\Models\Suppression;
use App\Models\User;
use App\Models\WorkflowTransition;
use App\Services\Audit\AuditLogger;
use App\Services\Consent\ConsentService;
use App\Services\Consent\SuppressionService;
use App\Services\Settings\Settings;
use App\Support\Crypto\PhoneHasher;
use App\Support\Phone\NigerianPhoneNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Data-subject requests (NDPA 2023 Part VI): received → verifying → in progress
 * → completed / rejected. Requests from the person's own phone (USSD, SMS, or
 * web after a one-time code) count as identity-verified.
 *
 * Withdrawal of consent and objection/restriction take effect immediately on
 * receipt (suppression); erasure and access are fulfilled by the DPO.
 */
final class DataSubjectRequestService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly ConsentService $consent,
        private readonly SuppressionService $suppressions,
        private readonly ErasureService $erasure,
    ) {}

    public function submit(DsrType $type, string $phone, RequestChannel $channel, ?string $details, bool $identityVerified, ?User $by = null): DataSubjectRequest
    {
        $e164 = NigerianPhoneNumber::normalise($phone) ?? throw ValidationException::withMessages(['phone' => __('validation.nigerian_mobile')]);
        $hash = PhoneHasher::hash($e164);
        $supporter = Supporter::query()->where('phone_hash', $hash)->first();

        $request = DB::transaction(function () use ($type, $e164, $hash, $channel, $details, $identityVerified, $by, $supporter): DataSubjectRequest {
            $request = DataSubjectRequest::query()->create([
                'uuid' => (string) Str::uuid(),
                'reference' => $this->newReference(),
                'type' => $type,
                'status' => $identityVerified ? DsrStatus::InProgress : DsrStatus::Verifying,
                'channel' => $channel,
                'phone_hash' => $hash,
                'phone_encrypted' => $e164,
                'supporter_id' => $supporter?->id,
                'details' => $details === null ? null : mb_substr($details, 0, 2000),
                'identity_verified_at' => $identityVerified ? now() : null,
                'verification_method' => $identityVerified ? 'own_phone:'.$channel->value : null,
                'due_at' => now()->addDays((int) app(Settings::class)->get('privacy.dsr_response_days')),
            ]);

            WorkflowTransition::record($request, null, $request->status->value, $by, 'Received via '.$channel->label());

            // Requests to stop processing take effect at once, before any review.
            match ($type) {
                DsrType::WithdrawConsent => $this->consent->withdrawAll($hash, null, $channel, $supporter, ['dsr' => $request->reference], $by),
                DsrType::Objection, DsrType::Restriction, DsrType::Erasure => $this->stopProcessing($hash, $supporter, $type, $channel, $by),
                default => null,
            };

            $this->audit->record('dsr.received', $request, null, ['type' => $type->value, 'channel' => $channel->value], $by);

            return $request;
        });

        SendTransactionalSms::dispatch(
            $e164,
            "We received your data request (ref {$request->reference}). We will respond by ".$request->due_at->timezone(config('campaign.display_timezone'))->format('j M Y').'.',
            'dsr.received',
        );

        return $request;
    }

    public function markIdentityVerified(DataSubjectRequest $request, User $by, string $method): void
    {
        $this->move($request, DsrStatus::InProgress, $by, 'Identity verified: '.$method, ['identity_verified_at' => now(), 'verification_method' => mb_substr($method, 0, 40)]);
    }

    public function assign(DataSubjectRequest $request, User $assignee, User $by): void
    {
        $request->forceFill(['assigned_to' => $assignee->id])->save();
        $this->audit->record('dsr.assigned', $request, null, ['assignee' => $assignee->id], $by);
    }

    /** Builds the access-request package: everything held about the person, as JSON. */
    public function prepareAccessPackage(DataSubjectRequest $request, User $by): string
    {
        $this->assertType($request, DsrType::Access);
        $supporter = $request->supporter;

        $package = [
            'reference' => $request->reference,
            'generated_at' => now()->toIso8601String(),
            'controller' => config('app.name'),
            'supporter' => $supporter ? [
                'reference' => $supporter->reference,
                'full_name' => $supporter->full_name,
                'phone' => $supporter->phone(),
                'area' => $supporter->areaLabel(),
                'preferred_language' => $supporter->preferred_language,
                'source' => $supporter->source->label(),
                'registered_at' => $supporter->created_at->toIso8601String(),
                'verification_status' => $supporter->verification_status->label(),
                'wants_to_volunteer' => $supporter->wants_to_volunteer,
                'retention_until' => $supporter->retention_until->toDateString(),
            ] : null,
            'consent_history' => ConsentRecord::query()->where('phone_hash', $request->phone_hash)->orderBy('recorded_at')->get()
                ->map(fn ($c) => ['channel' => $c->channel->label(), 'purpose' => $c->purpose->label(), 'decision' => $c->status->label(), 'notice_version' => $c->notice_version, 'via' => $c->source->label(), 'at' => $c->recorded_at->toIso8601String()])->all(),
            'contacts' => $supporter ? $supporter->contactAttempts()->orderBy('occurred_at')->get()
                ->map(fn ($a) => ['channel' => $a->channel->label(), 'outcome' => $a->outcome->label(), 'notes' => $a->notes_encrypted, 'at' => $a->occurred_at->toIso8601String()])->all() : [],
            'suppressions' => Suppression::query()->where('phone_hash', $request->phone_hash)->get()
                ->map(fn ($s) => ['channel' => $s->channel?->label() ?? 'All channels', 'reason' => $s->reason->label(), 'since' => $s->created_at->toIso8601String(), 'lifted' => $s->lifted_at?->toIso8601String()])->all(),
        ];

        $disk = (string) config('campaign.privacy.exports_disk');
        $path = "dsr/{$request->uuid}.json";
        Storage::disk($disk)->put($path, json_encode($package, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $request->forceFill(['export_path' => $path])->save();
        $this->audit->record('dsr.access_package_prepared', $request, actor: $by);

        return $path;
    }

    public function complete(DataSubjectRequest $request, User $by, string $resolution): void
    {
        if ($request->identity_verified_at === null) {
            throw ValidationException::withMessages(['resolution' => __('privacy.verify_first')]);
        }

        DB::transaction(function () use ($request, $by, $resolution): void {
            if ($request->type === DsrType::Erasure && $request->supporter && ! $request->supporter->isErased()) {
                $this->erasure->anonymise($request->supporter, $by, 'DSR '.$request->reference, source: $request->channel);
            }

            if (in_array($request->type, [DsrType::Objection, DsrType::Restriction], true) && $request->supporter) {
                $request->supporter->forceFill(['processing_restricted' => true])->save();
            }

            $this->move($request, DsrStatus::Completed, $by, $resolution, ['resolution' => $resolution, 'completed_at' => now(), 'completed_by' => $by->id]);
        });

        SendTransactionalSms::dispatch((string) $request->phone_encrypted, "Your data request {$request->reference} has been completed. Contact us if you have questions.", 'dsr.completed');
    }

    public function reject(DataSubjectRequest $request, User $by, string $reason): void
    {
        $this->move($request, DsrStatus::Rejected, $by, $reason, ['resolution' => $reason, 'completed_at' => now(), 'completed_by' => $by->id]);
        SendTransactionalSms::dispatch((string) $request->phone_encrypted, "Your data request {$request->reference} could not be completed: {$reason}", 'dsr.rejected');
    }

    private function stopProcessing(string $hash, ?Supporter $supporter, DsrType $type, RequestChannel $channel, ?User $by): void
    {
        $this->suppressions->suppress($hash, null, $type === DsrType::Erasure ? SuppressionReason::Erasure : SuppressionReason::Objection, $channel, $by);
        $supporter?->forceFill(['processing_restricted' => true])->save();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function move(DataSubjectRequest $request, DsrStatus $to, User $by, string $note, array $attributes = []): void
    {
        if (! $request->isOpen()) {
            throw ValidationException::withMessages(['status' => __('privacy.request_closed')]);
        }

        $from = $request->status;
        $request->forceFill(['status' => $to] + $attributes)->save();
        WorkflowTransition::record($request, $from->value, $to->value, $by, $note);
        $this->audit->record('dsr.'.$to->value, $request, null, ['note' => $note], $by);
    }

    private function assertType(DataSubjectRequest $request, DsrType $type): void
    {
        if ($request->type !== $type) {
            throw ValidationException::withMessages(['type' => 'This action applies to '.$type->label().' requests only.']);
        }
    }

    private function newReference(): string
    {
        do {
            $reference = 'DSR-'.now()->format('y').'-'.strtoupper(Str::random(6));
        } while (DataSubjectRequest::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
