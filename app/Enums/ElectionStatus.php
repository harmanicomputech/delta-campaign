<?php

declare(strict_types=1);

namespace App\Enums;

/** Election lifecycle. Agents can only report while an election is active. */
enum ElectionStatus: string
{
    case Setup = 'setup';
    case Active = 'active';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Setup => 'Setting up',
            self::Active => 'Active',
            self::Closed => 'Closed',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Setup => 'neutral',
            self::Active => 'success',
            self::Closed => 'neutral',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_combine(array_column(self::cases(), 'value'), array_map(fn (self $c): string => $c->label(), self::cases()));
    }
}
