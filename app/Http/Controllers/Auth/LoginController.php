<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\LoginEventType;
use App\Enums\OtpPurpose;
use App\Http\Controllers\Controller;
use App\Models\OtpChallenge;
use App\Models\User;
use App\Rules\NigerianMobileNumber;
use App\Services\Authentication\LoginRecorder;
use App\Services\Authentication\OtpService;
use App\Services\Authentication\SignInService;
use App\Support\Phone\NigerianPhoneNumber;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Phone number → one-time code → (second factor) → workspace.
 * Every response is identical whether or not the number is registered.
 */
final class LoginController extends Controller
{
    private const string SESSION_CHALLENGE = 'auth.challenge';

    public function __construct(
        private readonly OtpService $otp,
        private readonly SignInService $signIn,
        private readonly LoginRecorder $recorder,
    ) {}

    public function create(): View
    {
        return view('pages.auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate(['phone' => ['required', 'string', 'max:20', new NigerianMobileNumber]]);

        $challenge = $this->otp->request($validated['phone'], OtpPurpose::Login, $request->ip());

        $request->session()->put(self::SESSION_CHALLENGE, [
            'id' => $challenge->id,
            'masked' => NigerianPhoneNumber::mask((string) NigerianPhoneNumber::normalise($validated['phone'])),
            'phone' => encrypt((string) NigerianPhoneNumber::normalise($validated['phone'])),
        ]);

        return redirect()->route('login.verify');
    }

    public function showVerify(Request $request): View|RedirectResponse
    {
        $pending = $request->session()->get(self::SESSION_CHALLENGE);

        if (! is_array($pending)) {
            return redirect()->route('login');
        }

        $challenge = OtpChallenge::query()->find($pending['id']);

        return view('pages.auth.verify', [
            'masked' => $pending['masked'],
            'resendIn' => $challenge ? max(0, (int) config('campaign.auth.otp_resend_seconds') - (int) $challenge->created_at->diffInSeconds(now())) : 0,
        ]);
    }

    public function verify(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:12']]);
        $pending = $request->session()->get(self::SESSION_CHALLENGE);
        $challenge = is_array($pending) ? OtpChallenge::query()->find($pending['id']) : null;

        if ($challenge === null || $challenge->purpose !== OtpPurpose::Login) {
            return redirect()->route('login')->with('warning', __('auth.code_expired'));
        }

        if (! $this->otp->verify($challenge, (string) $request->input('code'))) {
            $this->recorder->record(LoginEventType::CodeFailed, $challenge->user, $challenge->phone_hash);

            $message = $challenge->refresh()->isUsable() ? __('auth.code_invalid') : __('auth.code_expired');

            return back()->withErrors(['code' => $message]);
        }

        $request->session()->forget(self::SESSION_CHALLENGE);
        $user = $challenge->user;

        // A code is only ever sent to active accounts, but the account may have been suspended since.
        if (! $user instanceof User || ! $user->isActive()) {
            if ($user instanceof User) {
                $this->recorder->record(LoginEventType::BlockedSuspended, $user);
            }

            return redirect()->route('login')->withErrors(['phone' => __('auth.cannot_sign_in')]);
        }

        if ($user->hasTwoFactorEnabled()) {
            $request->session()->put(SignInService::SESSION_PENDING, ['user_id' => $user->id, 'expires' => now()->addMinutes(5)->getTimestamp()]);

            return redirect()->route('two-factor.challenge');
        }

        $this->signIn->complete($request, $user);

        return redirect()->intended(route('workspace.dashboard'));
    }

    public function resend(Request $request): RedirectResponse
    {
        $pending = $request->session()->get(self::SESSION_CHALLENGE);

        if (! is_array($pending)) {
            return redirect()->route('login');
        }

        $challenge = $this->otp->request(decrypt($pending['phone']), OtpPurpose::Login, $request->ip());
        $request->session()->put(self::SESSION_CHALLENGE.'.id', $challenge->id);

        return redirect()->route('login.verify')->with('info', __('auth.code_resent'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->signIn->signOut($request);

        return redirect()->route('login')->with('success', __('auth.signed_out'));
    }
}
