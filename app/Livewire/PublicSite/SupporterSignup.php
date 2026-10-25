<?php

declare(strict_types=1);

namespace App\Livewire\PublicSite;

use App\Enums\SupporterSource;
use App\Livewire\Concerns\GuardsPublicForms;
use App\Livewire\Concerns\VerifiesPhone;
use App\Models\Lga;
use App\Models\PrivacyNotice;
use App\Models\Ward;
use App\Services\Consent\NoPublishedNotice;
use App\Services\Supporters\SupporterRegistrar;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

/** Public supporter registration: phone verified by one-time code, consent per channel, 18+ attestation. */
final class SupporterSignup extends Component
{
    use GuardsPublicForms, VerifiesPhone;

    public string $fullName = '';

    public ?int $lgaId = null;

    public ?int $wardId = null;

    public bool $consentSms = false;

    public bool $consentWhatsapp = false;

    public bool $volunteer = false;

    public bool $adult = false;

    public ?string $reference = null;

    public bool $existing = false;

    public function updatedLgaId(): void
    {
        $this->wardId = null;
    }

    public function register(SupporterRegistrar $registrar): void
    {
        $this->validate([
            'fullName' => ['required', 'string', 'min:2', 'max:150', "regex:/^[\\p{L}][\\p{L} .'-]+$/u"],
            'lgaId' => ['required', 'integer', Rule::exists('lgas', 'id')],
            'wardId' => ['nullable', 'integer', Rule::exists('wards', 'id')->where('lga_id', $this->lgaId)],
            'adult' => ['accepted'],
        ], [], ['fullName' => __('supporters.full_name'), 'lgaId' => 'LGA', 'wardId' => __('volunteers.ward'), 'adult' => __('public.age_confirmation')]);

        if ($this->verifiedPhone === null) {
            $this->addError('phone', __('public.verify_first'));

            return;
        }

        $this->guard('signup', 5);

        try {
            $result = $registrar->register(
                ['full_name' => $this->fullName, 'phone' => $this->verifiedPhone, 'lga_id' => $this->lgaId, 'ward_id' => $this->wardId, 'preferred_language' => app()->getLocale() === 'pcm' ? 'pcm' : 'en', 'wants_to_volunteer' => $this->volunteer],
                SupporterSource::Web,
                ['sms' => $this->consentSms, 'whatsapp' => $this->consentWhatsapp],
                true,
                evidence: ['form' => 'website', 'notice' => PrivacyNotice::current()?->version, 'ip_hash' => substr(hash('sha256', (string) request()->ip()), 0, 16)],
                phoneVerified: true,
            );
        } catch (NoPublishedNotice) {
            $this->addError('form', __('public.not_open'));

            return;
        }

        $this->reference = $result->supporter->reference;
        $this->existing = ! $result->created;
    }

    public function render(): View
    {
        return view('livewire.public.supporter-signup', [
            'lgas' => Lga::query()->orderBy('name')->pluck('name', 'id'),
            'wards' => $this->lgaId ? Ward::query()->where('lga_id', $this->lgaId)->orderBy('name')->pluck('name', 'id') : collect(),
            'notice' => PrivacyNotice::current(),
            'masked' => $this->maskedVerifiedPhone(),
        ]);
    }
}
