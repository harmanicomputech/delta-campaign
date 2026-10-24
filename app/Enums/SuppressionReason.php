<?php

declare(strict_types=1);

namespace App\Enums;

/** Why a number must not be contacted. */
enum SuppressionReason: string
{
    case OptOut = 'opt_out';
    case Erasure = 'erasure';
    case Objection = 'objection';
    case Complaint = 'complaint';
    case InvalidNumber = 'invalid_number';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::OptOut => 'Opted out',
            self::Erasure => 'Erasure request',
            self::Objection => 'Objection / restriction',
            self::Complaint => 'Complaint',
            self::InvalidNumber => 'Invalid number',
            self::Admin => 'Administrative',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_combine(array_column(self::cases(), 'value'), array_map(fn (self $c): string => $c->label(), self::cases()));
    }
}
