<?php

declare(strict_types=1);

namespace App\Enums;

enum LoginEventType: string
{
    case CodeRequested = 'code_requested';
    case CodeRequestThrottled = 'code_request_throttled';
    case CodeFailed = 'code_failed';
    case ChallengeLocked = 'challenge_locked';
    case LoginSucceeded = 'login_succeeded';
    case TwoFactorFailed = 'two_factor_failed';
    case TwoFactorSucceeded = 'two_factor_succeeded';
    case RecoveryCodeUsed = 'recovery_code_used';
    case NewDevice = 'new_device';
    case BlockedSuspended = 'blocked_suspended';
    case PinFailed = 'pin_failed';
    case PinLocked = 'pin_locked';
    case Unlocked = 'unlocked';
    case Logout = 'logout';
    case SessionRevoked = 'session_revoked';

    public function label(): string
    {
        return ucfirst(str_replace('_', ' ', $this->value));
    }

    public function tone(): string
    {
        return match ($this) {
            self::LoginSucceeded, self::TwoFactorSucceeded, self::Unlocked => 'success',
            self::CodeFailed, self::TwoFactorFailed, self::PinFailed => 'warning',
            self::ChallengeLocked, self::BlockedSuspended, self::PinLocked, self::CodeRequestThrottled => 'danger',
            self::NewDevice, self::RecoveryCodeUsed => 'accent',
            default => 'neutral',
        };
    }

    public function isSuspicious(): bool
    {
        return in_array($this, [self::ChallengeLocked, self::PinLocked, self::BlockedSuspended, self::CodeRequestThrottled, self::NewDevice, self::RecoveryCodeUsed], true);
    }
}
