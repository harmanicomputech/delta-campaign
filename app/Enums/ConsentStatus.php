<?php

declare(strict_types=1);

namespace App\Enums;

/** A consent decision. Records are append-only; the latest decision applies. */
enum ConsentStatus: string
{
    case Granted = 'granted';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::Granted => 'Granted',
            self::Withdrawn => 'Withdrawn',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Granted => 'success',
            self::Withdrawn => 'neutral',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_combine(array_column(self::cases(), 'value'), array_map(fn (self $c): string => $c->label(), self::cases()));
    }
}
