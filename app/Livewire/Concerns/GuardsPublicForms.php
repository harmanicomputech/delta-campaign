<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

/**
 * Anti-spam for public forms, without third-party captchas: a hidden honeypot
 * field, a minimum time to fill the form, and a per-IP rate limit.
 */
trait GuardsPublicForms
{
    /** Honeypot: hidden from people, filled by bots. */
    public string $website = '';

    #[Locked]
    public int $startedAt = 0;

    public function mountGuardsPublicForms(): void
    {
        $this->startedAt = now()->getTimestamp();
    }

    protected function guard(string $form, int $perHour = 10): void
    {
        if ($this->website !== '' || now()->getTimestamp() - $this->startedAt < 3) {
            throw ValidationException::withMessages(['form' => __('public.spam_blocked')]);
        }

        $key = 'public-form:'.$form.':'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, $perHour)) {
            throw ValidationException::withMessages(['form' => __('public.too_many', ['minutes' => (int) ceil(RateLimiter::availableIn($key) / 60)])]);
        }
        RateLimiter::hit($key, 3600);
    }
}
