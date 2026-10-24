<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Services\Authentication\SignInService;

/** For Livewire actions that need a recent PIN/code confirmation (like RequirePinConfirmation). */
trait ChecksRecentConfirmation
{
    protected function recentlyConfirmed(): bool
    {
        $at = (int) session(SignInService::SESSION_PIN_CONFIRMED, 0);

        return now()->getTimestamp() - $at <= (int) config('campaign.auth.pin_confirm_minutes') * 60;
    }

    /** Sends the person to confirm, then back to this page. */
    protected function requireConfirmation(): void
    {
        session()->put('url.intended', url()->previous());
        $this->redirectRoute('pin.confirm');
    }
}
