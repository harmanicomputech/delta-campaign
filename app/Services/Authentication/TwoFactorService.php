<?php

declare(strict_types=1);

namespace App\Services\Authentication;

use App\Enums\LoginEventType;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/** TOTP enrolment, verification (with replay protection) and recovery codes. */
final class TwoFactorService
{
    public function __construct(private readonly AuditLogger $audit, private readonly LoginRecorder $recorder) {}

    /** Starts (or restarts) enrolment with a new unconfirmed secret. */
    public function beginEnrolment(User $user): string
    {
        $secret = Totp::generateSecret();
        $user->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => null, 'two_factor_recovery_codes' => null])->save();

        return $secret;
    }

    /**
     * Confirms enrolment with a code from the app. Returns recovery codes (shown once) or null.
     *
     * @return list<string>|null
     */
    public function confirmEnrolment(User $user, string $code): ?array
    {
        if ($user->two_factor_secret === null || ! $this->checkCode($user, $code)) {
            return null;
        }

        $codes = self::generateRecoveryCodes();
        $user->forceFill(['two_factor_confirmed_at' => now(), 'two_factor_recovery_codes' => $codes])->save();
        $this->audit->record('auth.two_factor_enabled', $user, actor: $user);

        return $codes;
    }

    /** Verifies an app code or a single-use recovery code during sign-in. */
    public function verify(User $user, string $input): bool
    {
        if (! $user->hasTwoFactorEnabled()) {
            return false;
        }

        if ($this->checkCode($user, $input)) {
            $this->recorder->record(LoginEventType::TwoFactorSucceeded, $user);

            return true;
        }

        $normalised = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $input));
        $codes = $user->two_factor_recovery_codes ?? [];

        foreach ($codes as $index => $recovery) {
            if (hash_equals(str_replace('-', '', $recovery), $normalised)) {
                unset($codes[$index]);
                $user->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();
                $this->recorder->record(LoginEventType::RecoveryCodeUsed, $user, context: ['remaining' => count($codes)]);
                $this->audit->record('auth.recovery_code_used', $user, actor: $user, context: ['remaining' => count($codes)]);

                return true;
            }
        }

        $this->recorder->record(LoginEventType::TwoFactorFailed, $user);

        return false;
    }

    /** @return list<string> */
    public function regenerateRecoveryCodes(User $user): array
    {
        $codes = self::generateRecoveryCodes();
        $user->forceFill(['two_factor_recovery_codes' => $codes])->save();
        $this->audit->record('auth.recovery_codes_regenerated', $user);

        return $codes;
    }

    public function reset(User $user): void
    {
        $user->forceFill(['two_factor_secret' => null, 'two_factor_confirmed_at' => null, 'two_factor_recovery_codes' => null])->save();
        $this->audit->record('auth.two_factor_reset', $user);
    }

    /** @return list<string> */
    public static function generateRecoveryCodes(int $count = 8): array
    {
        return array_map(fn (): string => Str::upper(Str::random(4).'-'.Str::random(4)), range(1, $count));
    }

    private function checkCode(User $user, string $code): bool
    {
        $key = "totp:last-step:{$user->id}";
        $step = Totp::verify((string) $user->two_factor_secret, $code, Cache::get($key));

        if ($step === null) {
            return false;
        }

        Cache::put($key, $step, now()->addMinutes(5));

        return true;
    }
}
