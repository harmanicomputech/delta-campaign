<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Authentication\SignInService;
use App\Services\Authentication\Totp;
use App\Services\Authentication\TwoFactorService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

final class TwoFactorController extends Controller
{
    public function __construct(private readonly TwoFactorService $twoFactor, private readonly SignInService $signIn) {}

    /** Second step of sign-in for accounts with an authenticator app. */
    public function challenge(Request $request): View|RedirectResponse
    {
        return $this->pendingUser($request) ? view('pages.auth.two-factor-challenge') : redirect()->route('login');
    }

    public function verifyChallenge(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:20']]);
        $user = $this->pendingUser($request);

        if ($user === null) {
            return redirect()->route('login')->with('warning', __('auth.code_expired'));
        }

        $key = "two-factor:{$user->id}";
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['code' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($key)])]);
        }

        if (! $this->twoFactor->verify($user, (string) $request->input('code'))) {
            RateLimiter::hit($key, 300);

            return back()->withErrors(['code' => __('auth.two_factor_invalid')]);
        }

        RateLimiter::clear($key);
        $this->signIn->complete($request, $user);

        return redirect()->intended(route('workspace.dashboard'));
    }

    /** Enrolment for privileged users (enforced by EnsureTwoFactorEnrolled). */
    public function setup(Request $request): View|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasTwoFactorEnabled() && ! $request->session()->has('two_factor.recovery_codes')) {
            return redirect()->route('workspace.account');
        }

        $codes = $request->session()->get('two_factor.recovery_codes');
        $secret = $user->two_factor_secret ?? $this->twoFactor->beginEnrolment($user);

        return view('pages.auth.two-factor-setup', [
            'secret' => $secret,
            'uri' => Totp::uri($secret, $user->maskedPhone(), (string) config('app.name')),
            'recoveryCodes' => $codes,
        ]);
    }

    public function confirmSetup(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:10']]);
        /** @var User $user */
        $user = $request->user();

        $codes = $this->twoFactor->confirmEnrolment($user, (string) $request->input('code'));

        if ($codes === null) {
            return back()->withErrors(['code' => __('auth.two_factor_invalid')]);
        }

        return redirect()->route('two-factor.setup')->with('two_factor.recovery_codes', $codes);
    }

    private function pendingUser(Request $request): ?User
    {
        $pending = $request->session()->get(SignInService::SESSION_PENDING);

        if (! is_array($pending) || ($pending['expires'] ?? 0) < now()->getTimestamp()) {
            $request->session()->forget(SignInService::SESSION_PENDING);

            return null;
        }

        $user = User::query()->find($pending['user_id']);

        return $user?->isActive() ? $user : null;
    }
}
