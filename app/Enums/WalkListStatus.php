<?php

declare(strict_types=1);

namespace App\Enums;

/** Walk-list lifecycle. */
enum WalkListStatus: string
{
    case Draft = 'draft';
    case Assigned = 'assigned';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Assigned => 'Assigned',
            self::InProgress => 'In progress',
            self::Completed => 'Completed',
            self::Archived => 'Archived',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::Assigned => 'info',
            self::InProgress => 'brand',
            self::Completed => 'success',
            self::Archived => 'neutral',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_combine(array_column(self::cases(), 'value'), array_map(fn (self $c): string => $c->label(), self::cases()));
    }
}
