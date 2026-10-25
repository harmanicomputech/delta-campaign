<?php

declare(strict_types=1);

namespace App\Services\Ussd;

use App\Enums\ConsentChannel;
use App\Enums\ConsentPurpose;
use App\Enums\ConsentStatus;
use App\Enums\DsrType;
use App\Enums\RequestChannel;
use App\Enums\SupporterSource;
use App\Models\Lga;
use App\Models\PollingUnit;
use App\Models\Supporter;
use App\Models\Volunteer;
use App\Models\Ward;
use App\Services\Callbacks\CallbackService;
use App\Services\Consent\ConsentService;
use App\Services\Consent\NoPublishedNotice;
use App\Services\Privacy\DataSubjectRequestService;
use App\Services\Supporters\SupporterRegistrar;
use App\Services\Volunteers\VolunteerService;
use App\Support\Crypto\PhoneHasher;
use Illuminate\Validation\ValidationException;

/**
 * The work behind each USSD flow. Every action is a quick, indexed write or read;
 * SMS confirmations are queued, never sent inside the callback.
 */
final class UssdActions
{
    public function __construct(
        private readonly SupporterRegistrar $registrar,
        private readonly VolunteerService $volunteers,
        private readonly CallbackService $callbacks,
        private readonly ConsentService $consent,
        private readonly DataSubjectRequestService $requests,
    ) {}

    public function join(UssdSession $s): Next
    {
        $hash = PhoneHasher::hash($s->phone);

        if ($existing = Supporter::query()->where('phone_hash', $hash)->whereNull('erased_at')->first()) {
            return Next::end($s->t('join_exists', ['ref' => $existing->reference]));
        }

        try {
            $result = $this->registrar->register(
                ['full_name' => (string) $s->data['name'], 'phone' => $s->phone, 'lga_id' => (int) $s->data['lga_id'], 'preferred_language' => $s->lang],
                SupporterSource::Ussd,
                ['sms' => (bool) ($s->data['sms'] ?? false)],
                true,
                evidence: ['channel' => 'ussd', 'session' => substr(hash('sha256', $s->id), 0, 16)],
                phoneVerified: true,
                sample: $s->simulated,
            );
        } catch (NoPublishedNotice) {
            return Next::end($s->t('not_open'));
        } catch (ValidationException) {
            return Next::end($s->t('error'));
        }

        return Next::end($s->t(($s->data['sms'] ?? false) ? 'join_done_sms' : 'join_done', ['name' => mb_strimwidth((string) $s->data['name'], 0, 30, '…'), 'ref' => $result->supporter->reference]));
    }

    public function volunteer(UssdSession $s): Next
    {
        if (Volunteer::query()->where('phone_hash', PhoneHasher::hash($s->phone))->exists()) {
            return Next::end($s->t('volunteer_exists'));
        }

        $this->volunteers->apply([
            'full_name' => (string) $s->data['name'],
            'phone' => $s->phone,
            'lga_id' => (int) $s->data['lga_id'],
            'ward_id' => isset($s->data['ward_id']) ? (int) $s->data['ward_id'] : null,
        ], 'ussd', sample: $s->simulated);

        return Next::end($s->t('volunteer_done'));
    }

    public function pollingUnit(UssdSession $s, PollingUnit $unit): Next
    {
        $unit->loadMissing(['ward:id,name,code', 'lga:id,name']);

        return Next::end($s->t('pu_details', [
            'name' => mb_strimwidth($unit->name, 0, 45, '…'),
            'code' => $unit->code,
            'ward' => mb_strimwidth((string) $unit->ward?->name, 0, 35, '…'),
            'lga' => (string) $unit->lga?->name,
        ]).($unit->is_sample ? "\n".$s->t('sample') : ''));
    }

    public function findUnit(string $code): ?PollingUnit
    {
        return PollingUnit::query()->where('code', strtoupper($code))->first();
    }

    public function projects(UssdSession $s, Lga $lga): Next
    {
        // Public project records arrive with the CMS (Phase 10); until then nothing is published.
        return Next::end($s->t('projects_none', ['lga' => $lga->name]));
    }

    public function callback(UssdSession $s): Next
    {
        ['request' => $request, 'created' => $created] = $this->callbacks->request(
            $s->phone, Lga::query()->findOrFail((int) $s->data['lga_id']), (string) $s->data['topic'], 'ussd', $s->lang, $s->simulated,
        );

        return Next::end($s->t($created ? 'callback_done' : 'callback_exists', ['ref' => $request->reference]));
    }

    public function preferences(UssdSession $s): Next
    {
        $hash = PhoneHasher::hash($s->phone);
        $supporter = Supporter::query()->where('phone_hash', $hash)->first();

        return match ($s->data['pref'] ?? null) {
            'stop' => $this->stop($s, $hash, $supporter),
            'resume' => $this->resume($s, $hash, $supporter),
            'delete' => $this->erase($s),
            default => Next::end($s->t('error')),
        };
    }

    private function stop(UssdSession $s, string $hash, ?Supporter $supporter): Next
    {
        $this->consent->withdrawAll($hash, null, RequestChannel::Ussd, $supporter, ['channel' => 'ussd']);

        return Next::end($s->t('stop_done'));
    }

    private function resume(UssdSession $s, string $hash, ?Supporter $supporter): Next
    {
        try {
            $this->consent->record($hash, ConsentChannel::Sms, ConsentPurpose::CampaignUpdates, ConsentStatus::Granted, RequestChannel::Ussd, $supporter, ['channel' => 'ussd']);
        } catch (NoPublishedNotice) {
            return Next::end($s->t('not_open'));
        }

        return Next::end($s->t('resume_done'));
    }

    private function erase(UssdSession $s): Next
    {
        // The request comes from the person's own phone, so their identity is treated as verified.
        $request = $this->requests->submit(DsrType::Erasure, $s->phone, RequestChannel::Ussd, null, true);

        return Next::end($s->t('delete_done', ['ref' => $request->reference, 'days' => (int) config('campaign.privacy.dsr_response_days')]));
    }

    /** @return array<int, string> */
    public function lgas(): array
    {
        return Lga::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    /** @return array<int, string> */
    public function wards(int $lgaId): array
    {
        return Ward::query()->where('lga_id', $lgaId)->orderBy('name')->pluck('name', 'id')->all();
    }

    /** @return array<int, string> */
    public function units(int $wardId): array
    {
        return PollingUnit::query()->where('ward_id', $wardId)->orderBy('code')->get(['id', 'code', 'name'])
            ->mapWithKeys(fn (PollingUnit $u) => [$u->id => $u->name])->all();
    }
}
