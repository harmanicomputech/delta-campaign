<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Rules\NigerianMobileNumber;
use App\Services\PublicSite\PublicVerification;
use Illuminate\Validation\ValidationException;

/**
 * Two-step phone check for public forms: send a one-time code, then confirm it.
 * Components read $verifiedPhone once confirmed.
 */
trait VerifiesPhone
{
    public string $phone = '';

    public string $code = '';

    public bool $codeSent = false;

    public ?string $verifiedPhone = null;

    public function mountVerifiesPhone(): void
    {
        $this->verifiedPhone = app(PublicVerification::class)->verifiedPhone();
    }

    public function sendCode(PublicVerification $verification): void
    {
        $this->validate(['phone' => ['required', new NigerianMobileNumber]], [], ['phone' => __('supporters.phone')]);
        $this->guard('otp', 6);
        $verification->send($this->phone, request()->ip());
        $this->codeSent = true;
        $this->dispatch('toast', type: 'info', title: __('public.code_sent'));
    }

    public function confirmCode(PublicVerification $verification): void
    {
        $this->validate(['code' => ['required', 'digits:6']], [], ['code' => __('public.code')]);

        try {
            $this->verifiedPhone = $verification->confirm($this->code);
        } catch (ValidationException $e) {
            $this->addError('code', collect($e->errors())->flatten()->first());

            return;
        }

        $this->reset('code', 'codeSent');
    }

    public function changeNumber(PublicVerification $verification): void
    {
        $verification->forget();
        $this->reset('verifiedPhone', 'code', 'codeSent');
    }

    protected function maskedVerifiedPhone(): string
    {
        return $this->verifiedPhone ? '+234 ••• ••• '.substr($this->verifiedPhone, -4) : '';
    }
}
