<?php

declare(strict_types=1);

namespace App\Enums;

/** Progress of one walk-list stop. */
enum WalkListEntryStatus: string
{
    case Pending = 'pending';
    case Visited = 'visited';
    case Skipped = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Visited => 'Visited',
            self::Skipped => 'Skipped',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'neutral',
            self::Visited => 'success',
            self::Skipped => 'warning',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_combine(array_column(self::cases(), 'value'), array_map(fn (self $c): string => $c->label(), self::cases()));
    }
}
