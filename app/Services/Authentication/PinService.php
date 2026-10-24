<?php

declare(strict_types=1);

namespace App\Services\Authentication;

use App\Enums\LoginEventType;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

/**
 * Short numeric PIN for re-entry (unlocking an idle session, confirming a
 * sensitive action). Stored only as a bcrypt hash, with lockout.
 */
final class PinService
{
    public function __construct(private readonly AuditLogger $audit, private readonly LoginRecorder $recorder) {}

    public static function weakness(string $pin): ?string
    {
        $min = (int) config('campaign.auth.pin_length_min');
        $max = (int) config('campaign.auth.pin_length_max');

        if (preg_match('/^\d{'.$min.','.$max.'}$/', $pin) !== 1) {
            return "Use {$min} to {$max} digits.";
        }

        if (count(array_unique(str_split($pin))) === 1) {
            return 'Avoid repeating the same digit.';
        }

        $ascending = '01234567890';
        $descending = '09876543210';
        if (str_contains($ascending, $pin) || str_contains($descending, $pin)) {
            return 'Avoid sequences such as 1234.';
        }

        return null;
    }

    public function set(User $user, string $pin): void
    {
        if ($reason = self::weakness($pin)) {
            throw new InvalidArgumentException($reason);
        }

        $user->forceFill([
            'pin_hash' => Hash::make($pin),
            'pin_set_at' => now(),
            'pin_failed_attempts' => 0,
            'pin_locked_until' => null,
        ])->save();

        $this->audit->record('auth.pin_set', $user);
    }

    public function isLocked(User $user): bool
    {
        return $user->pin_locked_until !== null && $user->pin_locked_until->isFuture();
    }

    public function verify(User $user, string $pin): bool
    {
        if ($user->pin_hash === null || $this->isLocked($user)) {
            return false;
        }

        if (Hash::check($pin, $user->pin_hash)) {
            $user->forceFill(['pin_failed_attempts' => 0, 'pin_locked_until' => null])->save();

            return true;
        }

        $attempts = $user->pin_failed_attempts + 1;
        $locked = $attempts >= (int) config('campaign.auth.pin_max_attempts');

        $user->forceFill([
            'pin_failed_attempts' => $locked ? 0 : $attempts,
            'pin_locked_until' => $locked ? now()->addMinutes((int) config('campaign.auth.pin_lockout_minutes')) : null,
        ])->save();

        $this->recorder->record($locked ? LoginEventType::PinLocked : LoginEventType::PinFailed, $user);

        return false;
    }
}
