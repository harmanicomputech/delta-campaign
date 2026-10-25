<?php

declare(strict_types=1);

namespace App\Livewire\PublicSite;

use App\Enums\ConsentChannel;
use App\Enums\ConsentPurpose;
use App\Enums\ConsentStatus;
use App\Enums\DsrStatus;
use App\Enums\DsrType;
use App\Enums\RequestChannel;
use App\Livewire\Concerns\GuardsPublicForms;
use App\Livewire\Concerns\VerifiesPhone;
use App\Models\DataSubjectRequest;
use App\Models\Supporter;
use App\Services\Consent\ConsentService;
use App\Services\Consent\NoPublishedNotice;
use App\Services\Privacy\DataSubjectRequestService;
use App\Support\Crypto\PhoneHasher;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Self-service communication preferences and data-subject requests. The person
 * proves they hold the number with a one-time code; changes take effect at once.
 */
final class PreferencesCentre extends Component
{
    use GuardsPublicForms, VerifiesPhone;

    public string $requestType = 'access';

    public string $details = '';

    public function setConsent(string $channel, bool $granted, ConsentService $consent): void
    {
        if ($this->verifiedPhone === null || ! in_array($channel, ['sms', 'whatsapp'], true)) {
            return;
        }

        $hash = PhoneHasher::hash($this->verifiedPhone);
        $supporter = Supporter::query()->where('phone_hash', $hash)->first();

        try {
            $consent->record($hash, ConsentChannel::from($channel), ConsentPurpose::CampaignUpdates, $granted ? ConsentStatus::Granted : ConsentStatus::Withdrawn, RequestChannel::Web, $supporter, ['form' => 'preferences']);
        } catch (NoPublishedNotice) {
            $this->dispatch('toast', type: 'error', title: __('public.not_open'));

            return;
        }

        $this->dispatch('toast', type: 'success', title: __('public.preferences_saved'));
    }

    public function stopAll(ConsentService $consent): void
    {
        if ($this->verifiedPhone === null) {
            return;
        }

        $hash = PhoneHasher::hash($this->verifiedPhone);
        $consent->withdrawAll($hash, null, RequestChannel::Web, Supporter::query()->where('phone_hash', $hash)->first(), ['form' => 'preferences']);
        $this->dispatch('toast', type: 'success', title: __('public.stopped_all'));
    }

    public function submitRequest(DataSubjectRequestService $requests): void
    {
        if ($this->verifiedPhone === null) {
            return;
        }

        $this->validate([
            'requestType' => [Rule::in(['access', 'rectification', 'erasure', 'objection', 'restriction'])],
            'details' => [$this->requestType === 'rectification' ? 'required' : 'nullable', 'string', 'max:2000'],
        ], [], ['details' => __('public.request_details')]);
        $this->guard('dsr', 5);

        $request = $requests->submit(DsrType::from($this->requestType), $this->verifiedPhone, RequestChannel::Web, $this->details !== '' ? $this->details : null, true);
        $this->reset('details');
        $this->dispatch('toast', type: 'success', title: __('public.request_received', ['ref' => $request->reference]));
    }

    public function render(ConsentService $consent): View
    {
        $summary = null;
        $requests = collect();

        if ($this->verifiedPhone !== null) {
            $hash = PhoneHasher::hash($this->verifiedPhone);
            $summary = [
                'sms' => $consent->isEligible($hash, ConsentChannel::Sms, ConsentPurpose::CampaignUpdates),
                'whatsapp' => $consent->isEligible($hash, ConsentChannel::Whatsapp, ConsentPurpose::CampaignUpdates),
            ];
            $requests = DataSubjectRequest::query()->where('phone_hash', $hash)->latest()->limit(5)->get();
        }

        return view('livewire.public.preferences-centre', [
            'summary' => $summary,
            'requests' => $requests,
            'masked' => $this->maskedVerifiedPhone(),
            'types' => collect(['access', 'rectification', 'erasure', 'objection', 'restriction'])->mapWithKeys(fn ($t) => [$t => DsrType::from($t)->label()])->all(),
            'open' => [DsrStatus::Received, DsrStatus::Verifying, DsrStatus::InProgress],
        ]);
    }
}
