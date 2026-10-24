<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\LoginEventType;
use App\Enums\OtpPurpose;
use App\Http\Controllers\Controller;
use App\Models\OtpChallenge;
use App\Models\User;
use App\Services\Authentication\LoginRecorder;
use App\Services\Authentication\OtpService;
use App\Services\Authentication\PinService;
use App\Services\Authentication\SignInService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Re-entry for a signed-in person: unlocking an idle session and confirming
 * sensitive actions, with the PIN or (without a PIN) a fresh one-time code.
 */
final class ReauthenticationController extends Controller
{
    public function __construct(
        private readonly PinService $pins,
        private readonly OtpService $otp,
        private readonly LoginRecorder $recorder,
    ) {}

    public function showUnlock(Request $request): View|RedirectResponse
    {
        if (! $request->session()->get(SignInService::SESSION_LOCKED)) {
            return redirect()->route('workspace.dashboard');
        }

        return view('pages.auth.reauthenticate', ['mode' => 'unlock'] + $this->viewData($request));
    }

    public function showConfirm(Request $request): View
    {
        return view('pages.auth.reauthenticate', ['mode' => 'confirm'] + $this->viewData($request));
    }

    public function sendCode(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $challenge = $this->otp->request((string) $user->phone, OtpPurpose::Reauthenticate, $request->ip());
        $request->session()->put('auth.reauth_challenge', $challenge->id);

        return back()->with('info', __('auth.code_sent_to', ['phone' => $user->maskedPhone()]));
    }

    public function submit(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $mode = $request->input('mode') === 'unlock' ? 'unlock' : 'confirm';
        $ok = false;

        if ($request->filled('pin')) {
            $ok = $this->pins->verify($user, (string) $request->input('pin'));
            $error = ['pin' => $this->pins->isLocked($user->refresh()) ? __('auth.pin_locked') : __('auth.pin_invalid')];
        } else {
            $request->validate(['code' => ['required', 'string', 'max:12']]);
            $challenge = OtpChallenge::query()->whereKey($request->session()->get('auth.reauth_challenge'))
                ->where('user_id', $user->id)->where('purpose', OtpPurpose::Reauthenticate)->first();
            $ok = $challenge !== null && $this->otp->verify($challenge, (string) $request->input('code'));
            $error = ['code' => __('auth.code_invalid')];
        }

        if (! $ok) {
            return back()->withErrors($error);
        }

        $request->session()->forget([SignInService::SESSION_LOCKED, 'auth.reauth_challenge']);
        $request->session()->put([
            SignInService::SESSION_LAST_ACTIVITY => now()->getTimestamp(),
            SignInService::SESSION_PIN_CONFIRMED => now()->getTimestamp(),
        ]);

        if ($mode === 'unlock') {
            $this->recorder->record(LoginEventType::Unlocked, $user);
        }

        return redirect()->intended(route('workspace.dashboard'));
    }

    /** @return array<string, mixed> */
    private function viewData(Request $request): array
    {
        /** @var User $user */
        $user = $request->user();

        return [
            'hasPin' => $user->pin_hash !== null && ! $this->pins->isLocked($user),
            'pinLocked' => $this->pins->isLocked($user),
            'codeSent' => $request->session()->has('auth.reauth_challenge'),
            'masked' => $user->maskedPhone(),
        ];
    }
}
