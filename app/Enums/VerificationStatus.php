<?php

declare(strict_types=1);

namespace App\Enums;

/** Record verification. Phone-verified means the person proved possession of the number. */
enum VerificationStatus: string
{
    case Unverified = 'unverified';
    case PhoneVerified = 'phone_verified';
    case Verified = 'verified';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Unverified => 'Unverified',
            self::PhoneVerified => 'Phone verified',
            self::Verified => 'Verified',
            self::Rejected => 'Rejected',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Unverified => 'neutral',
            self::PhoneVerified => 'info',
            self::Verified => 'success',
            self::Rejected => 'danger',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_combine(array_column(self::cases(), 'value'), array_map(fn (self $c): string => $c->label(), self::cases()));
    }
}
