<?php

declare(strict_types=1);

namespace App\Services\Supporters;

use App\Enums\ConsentChannel;
use App\Enums\ConsentPurpose;
use App\Enums\ConsentStatus;
use App\Enums\RequestChannel;
use App\Enums\SupporterSource;
use App\Enums\VerificationStatus;
use App\Models\PollingUnit;
use App\Models\Supporter;
use App\Models\User;
use App\Models\Ward;
use App\Services\Audit\AuditLogger;
use App\Services\Consent\ConsentService;
use App\Support\Crypto\PhoneHasher;
use App\Support\Phone\NigerianPhoneNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Registers supporters from any channel. Deduplicates on the keyed phone hash:
 * a repeat registration updates nothing personal, but records any new consent
 * decisions against the existing record.
 */
final class SupporterRegistrar
{
    public function __construct(private readonly ConsentService $consent, private readonly AuditLogger $audit) {}

    /**
     * @param  array{full_name: string, phone: string, lga_id?: int|null, ward_id?: int|null, polling_unit_id?: int|null, polling_unit_code?: string|null, preferred_language?: string, wants_to_volunteer?: bool}  $data
     * @param  array<string, bool>  $consents  channel value => granted?  (purpose: campaign updates)
     * @param  array<string, mixed>  $evidence
     */
    public function register(
        array $data,
        SupporterSource $source,
        array $consents,
        bool $adultConfirmed,
        ?User $by = null,
        array $evidence = [],
        bool $phoneVerified = false,
        bool $sample = false,
    ): RegistrationResult {
        if (! $adultConfirmed) {
            throw ValidationException::withMessages(['adult' => __('supporters.adult_required')]);
        }

        $e164 = NigerianPhoneNumber::normalise($data['phone']) ?? throw ValidationException::withMessages(['phone' => __('validation.nigerian_mobile')]);
        $hash = PhoneHasher::hash($e164);
        [$lgaId, $wardId, $unitId] = $this->resolveLocation($data);

        return DB::transaction(function () use ($data, $source, $consents, $by, $evidence, $phoneVerified, $sample, $e164, $hash, $lgaId, $wardId, $unitId): RegistrationResult {
            $existing = Supporter::query()->where('phone_hash', $hash)->lockForUpdate()->first();
            $created = $existing === null;

            $supporter = $existing ?? new Supporter([
                'full_name' => mb_substr(trim((string) preg_replace('/\s+/', ' ', $data['full_name'])), 0, 150),
                'lga_id' => $lgaId,
                'ward_id' => $wardId,
                'polling_unit_id' => $unitId,
                'preferred_language' => in_array($data['preferred_language'] ?? 'en', ['en', 'pcm'], true) ? ($data['preferred_language'] ?? 'en') : 'en',
                'source' => $source,
                'wants_to_volunteer' => (bool) ($data['wants_to_volunteer'] ?? false),
            ]);

            if ($created) {
                $supporter->setPhone($e164);
                $supporter->forceFill([
                    'registered_by' => $by?->id,
                    'adult_confirmed_at' => now(),
                    'is_sample' => $sample,
                ]);
            }

            if ($phoneVerified && $supporter->phone_verified_at === null) {
                $supporter->forceFill([
                    'phone_verified_at' => now(),
                    'verification_status' => $supporter->verification_status === VerificationStatus::Unverified ? VerificationStatus::PhoneVerified : $supporter->verification_status,
                ]);
            }

            if (! $created && ($data['wants_to_volunteer'] ?? false)) {
                $supporter->wants_to_volunteer = true;
            }

            $supporter->save();

            // Only grants are recorded at registration. An unticked box means "not asked / not given",
            // which already makes the person ineligible; it is not an opt-out and does not suppress.
            foreach ($consents as $channel => $granted) {
                if ($granted) {
                    $this->consent->record($hash, ConsentChannel::from($channel), ConsentPurpose::CampaignUpdates, ConsentStatus::Granted, RequestChannel::from($source->value), $supporter, $evidence, $by);
                }
            }

            $this->audit->record($created ? 'supporter.registered' : 'supporter.registration_repeated', $supporter, null, ['source' => $source->value], $by);

            return new RegistrationResult($supporter->refresh(), $created);
        });
    }

    /**
     * The most specific location wins; the database derives the rest.
     *
     * @param  array<string, mixed>  $data
     * @return array{0: int, 1: int|null, 2: int|null}
     */
    private function resolveLocation(array $data): array
    {
        if (! empty($data['polling_unit_code'])) {
            $unit = PollingUnit::query()->where('code', strtoupper(trim((string) $data['polling_unit_code'])))->first()
                ?? throw ValidationException::withMessages(['polling_unit_code' => __('supporters.unknown_polling_unit')]);

            return [$unit->lga_id, $unit->ward_id, $unit->id];
        }

        if (! empty($data['polling_unit_id'])) {
            $unit = PollingUnit::query()->findOrFail($data['polling_unit_id']);

            return [$unit->lga_id, $unit->ward_id, $unit->id];
        }

        if (! empty($data['ward_id'])) {
            $ward = Ward::query()->findOrFail($data['ward_id']);

            if (! empty($data['lga_id']) && (int) $data['lga_id'] !== $ward->lga_id) {
                throw ValidationException::withMessages(['ward_id' => __('supporters.ward_not_in_lga')]);
            }

            return [$ward->lga_id, $ward->id, null];
        }

        if (empty($data['lga_id'])) {
            throw ValidationException::withMessages(['lga_id' => __('supporters.lga_required')]);
        }

        return [(int) $data['lga_id'], null, null];
    }
}
