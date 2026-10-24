<?php

declare(strict_types=1);

namespace App\Livewire\Workspace\Account;

use App\Livewire\Concerns\ChecksRecentConfirmation;
use App\Models\Device;
use App\Models\User;
use App\Services\Authentication\PinService;
use App\Services\Authentication\SignInService;
use App\Services\Authentication\TwoFactorService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

final class SecurityPage extends Component
{
    use ChecksRecentConfirmation;

    public string $pin = '';

    public string $pinConfirmation = '';

    /** @var list<string>|null Shown once after regeneration. */
    public ?array $recoveryCodes = null;

    public function savePin(PinService $pins): void
    {
        if (! $this->recentlyConfirmed()) {
            $this->requireConfirmation();

            return;
        }

        $this->validate([
            'pin' => ['required', 'string', function (string $attribute, mixed $value, \Closure $fail): void {
                if ($reason = PinService::weakness((string) $value)) {
                    $fail($reason);
                }
            }],
            'pinConfirmation' => ['required', 'same:pin'],
        ], [], ['pinConfirmation' => 'PIN confirmation']);

        $pins->set($this->user(), $this->pin);
        $this->reset('pin', 'pinConfirmation');
        $this->dispatch('toast', type: 'success', title: __('account.pin_saved'));
    }

    public function revokeSession(string $sessionId, SignInService $sessions): void
    {
        if ($sessionId === session()->getId()) {
            return;
        }

        $sessions->revokeSession($this->user(), $sessionId, $this->user());
        unset($this->sessions);
        $this->dispatch('toast', type: 'success', title: __('account.session_ended'));
    }

    public function revokeOtherSessions(SignInService $sessions): void
    {
        $count = $sessions->revokeAllSessions($this->user(), session()->getId(), $this->user());
        unset($this->sessions);
        $this->dispatch('toast', type: 'success', title: trans_choice('account.sessions_ended', $count, ['count' => $count]));
    }

    public function revokeDevice(string $deviceId, SignInService $sessions): void
    {
        $device = $this->user()->devices()->whereKey($deviceId)->whereNull('revoked_at')->first();

        if ($device === null || $device->id === session(SignInService::SESSION_DEVICE)) {
            return;
        }

        $sessions->revokeDevice($device, $this->user());
        unset($this->devices, $this->sessions);
        $this->dispatch('toast', type: 'success', title: __('account.device_removed'));
    }

    public function regenerateRecoveryCodes(TwoFactorService $twoFactor): void
    {
        if (! $this->recentlyConfirmed()) {
            $this->requireConfirmation();

            return;
        }

        $this->recoveryCodes = $twoFactor->regenerateRecoveryCodes($this->user());
    }

    /** @return list<object> */
    #[Computed]
    public function sessions(): array
    {
        return app(SignInService::class)->sessions($this->user());
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, Device> */
    #[Computed]
    public function devices(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->user()->devices()->whereNull('revoked_at')->orderByDesc('last_seen_at')->get();
    }

    public function render(): View
    {
        return view('livewire.workspace.account.security-page', [
            'user' => $this->user(),
            'currentSession' => session()->getId(),
            'currentDevice' => session(SignInService::SESSION_DEVICE),
            'history' => $this->user()->loginEvents()->with('device')->latest('created_at')->limit(20)->get(),
            'deviceLabels' => $this->devices->mapWithKeys(fn (Device $d): array => [$d->id => $d->label()]),
        ])->title(__('account.title'))->layoutData(['breadcrumbs' => [__('shell.workspace') => route('workspace.dashboard'), __('account.title') => null]]);
    }

    private function user(): User
    {
        $user = Auth::user();
        assert($user instanceof User);

        return $user;
    }
}
