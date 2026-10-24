<?php

declare(strict_types=1);

namespace App\Services\Authentication;

use App\Enums\LoginEventType;
use App\Enums\OtpPurpose;
use App\Enums\UserStatus;
use App\Jobs\SendTransactionalSms;
use App\Models\OtpChallenge;
use App\Models\User;
use App\Support\Crypto\PhoneHasher;
use App\Support\Phone\NigerianPhoneNumber;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Issues and verifies one-time codes.
 *
 * Responses never reveal whether a number is registered: a challenge is always
 * created, but a code is only sent to active accounts (or, for public
 * verification and phone changes, to the number being verified). Only an HMAC
 * of each code is stored, attempts are capped, and requests are rate-limited
 * per number and per IP address.
 */
final class OtpService
{
    public function __construct(private readonly LoginRecorder $recorder) {}

    /**
     * @param  User|null  $forUser  For phone changes: the account the new number is being verified for.
     */
    public function request(string $phoneInput, OtpPurpose $purpose, ?string $ip, ?User $forUser = null): OtpChallenge
    {
        $e164 = NigerianPhoneNumber::normalise($phoneInput) ?? throw new \InvalidArgumentException('Invalid phone number.');
        $hash = PhoneHasher::hash($e164);

        $user = match ($purpose) {
            OtpPurpose::Login, OtpPurpose::Reauthenticate => User::query()->where('phone_hash', $hash)->first(),
            OtpPurpose::PhoneChange => $forUser,
            OtpPurpose::PublicVerification => null,
        };

        // Within the resend window, reuse the outstanding challenge and send nothing.
        $recent = OtpChallenge::query()
            ->where('phone_hash', $hash)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->where('created_at', '>', now()->subSeconds((int) config('campaign.auth.otp_resend_seconds')))
            ->latest()
            ->first();

        if ($recent !== null) {
            return $recent;
        }

        $code = $this->generateCode();
        $challenge = new OtpChallenge;
        $challenge->forceFill([
            'phone_hash' => $hash,
            'user_id' => $user?->id,
            'purpose' => $purpose,
            'code_hash' => '',
            'expires_at' => now()->addSeconds((int) config('campaign.auth.otp_ttl_seconds')),
            'ip' => $ip,
        ])->save();
        $challenge->forceFill(['code_hash' => self::hashCode($challenge->id, $code)])->save();

        if ($this->throttled($hash, $ip)) {
            $this->recorder->record(LoginEventType::CodeRequestThrottled, $user, $hash);

            return $challenge;
        }

        $deliver = match ($purpose) {
            OtpPurpose::Login, OtpPurpose::Reauthenticate => $user !== null && $user->status === UserStatus::Active,
            OtpPurpose::PhoneChange, OtpPurpose::PublicVerification => true,
        };

        if ($deliver) {
            SendTransactionalSms::dispatch($e164, $this->message($code, $purpose), 'otp.'.$purpose->value);
        }

        if (in_array($purpose, [OtpPurpose::Login, OtpPurpose::Reauthenticate], true)) {
            $this->recorder->record(LoginEventType::CodeRequested, $user, $hash, context: ['delivered' => $deliver]);
        }

        return $challenge;
    }

    /** Checks a code. Each call counts as an attempt; a consumed or expired challenge never verifies. */
    public function verify(OtpChallenge $challenge, string $code): bool
    {
        $code = preg_replace('/\D/', '', $code) ?? '';

        // Atomic increment so parallel guesses cannot exceed the attempt cap.
        $updated = OtpChallenge::query()
            ->whereKey($challenge->id)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->where('attempts', '<', (int) config('campaign.auth.otp_max_attempts'))
            ->increment('attempts');

        if ($updated === 0) {
            return false;
        }

        $challenge->refresh();

        if (! hash_equals($challenge->code_hash, self::hashCode($challenge->id, $code))) {
            if ($challenge->attempts >= (int) config('campaign.auth.otp_max_attempts')) {
                $this->recorder->record(LoginEventType::ChallengeLocked, $challenge->user, $challenge->phone_hash);
            }

            return false;
        }

        // Consume exactly once, even under concurrent correct submissions.
        return OtpChallenge::query()->whereKey($challenge->id)->whereNull('consumed_at')->update(['consumed_at' => now()]) === 1;
    }

    public static function hashCode(string $challengeId, string $code): string
    {
        return hash_hmac('sha256', $challengeId.'|'.$code, (string) config('app.key'));
    }

    private function generateCode(): string
    {
        $length = (int) config('campaign.auth.otp_length');

        return str_pad((string) random_int(0, 10 ** $length - 1), $length, '0', STR_PAD_LEFT);
    }

    private function throttled(string $phoneHash, ?string $ip): bool
    {
        $phoneKey = 'otp:phone:'.$phoneHash;
        $ipKey = 'otp:ip:'.($ip ?? 'none');

        if (RateLimiter::tooManyAttempts($phoneKey, (int) config('campaign.auth.otp_per_phone_per_hour'))
            || RateLimiter::tooManyAttempts($ipKey, (int) config('campaign.auth.otp_per_ip_per_hour'))) {
            return true;
        }

        RateLimiter::hit($phoneKey, 3600);
        RateLimiter::hit($ipKey, 3600);

        return false;
    }

    private function message(string $code, OtpPurpose $purpose): string
    {
        $app = (string) config('app.name');
        $minutes = (int) ceil((int) config('campaign.auth.otp_ttl_seconds') / 60);

        return match ($purpose) {
            OtpPurpose::PhoneChange => "{$code} is your code to confirm this number for {$app}. It expires in {$minutes} minutes. Do not share it.",
            OtpPurpose::PublicVerification => "{$code} is your {$app} verification code. It expires in {$minutes} minutes. Do not share it.",
            default => "{$code} is your {$app} sign-in code. It expires in {$minutes} minutes. Never share it, not even with campaign staff.",
        };
    }
}
