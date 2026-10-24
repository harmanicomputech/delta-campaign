<?php

declare(strict_types=1);

namespace App\Enums;

/** Data-subject request types (NDPA 2023, Part VI). */
enum DsrType: string
{
    case Access = 'access';
    case Rectification = 'rectification';
    case Erasure = 'erasure';
    case Objection = 'objection';
    case Restriction = 'restriction';
    case WithdrawConsent = 'withdraw_consent';

    public function label(): string
    {
        return match ($this) {
            self::Access => 'Access to my data',
            self::Rectification => 'Correct my data',
            self::Erasure => 'Delete my data',
            self::Objection => 'Object to processing',
            self::Restriction => 'Restrict processing',
            self::WithdrawConsent => 'Withdraw consent',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_combine(array_column(self::cases(), 'value'), array_map(fn (self $c): string => $c->label(), self::cases()));
    }
}
