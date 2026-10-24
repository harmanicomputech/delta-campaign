<?php

declare(strict_types=1);

namespace App\Enums;

/** Task lifecycle. */
enum TaskStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Blocked = 'blocked';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::InProgress => 'In progress',
            self::Blocked => 'Blocked',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Open => 'info',
            self::InProgress => 'brand',
            self::Blocked => 'warning',
            self::Completed => 'success',
            self::Cancelled => 'neutral',
        };
    }

    public function isClosed(): bool
    {
        return $this === self::Completed || $this === self::Cancelled;
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_combine(array_column(self::cases(), 'value'), array_map(fn (self $c): string => $c->label(), self::cases()));
    }
}
