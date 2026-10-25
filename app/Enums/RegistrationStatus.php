<?php

declare(strict_types=1);

namespace App\Enums;

/** Event registration state. */
enum RegistrationStatus: string
{
    case Registered = 'registered';
    case Attended = 'attended';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Registered => 'Registered',
            self::Attended => 'Attended',
            self::Cancelled => 'Cancelled',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Registered => 'info',
            self::Attended => 'success',
            self::Cancelled => 'neutral',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_combine(array_column(self::cases(), 'value'), array_map(fn (self $c): string => $c->label(), self::cases()));
    }
}
