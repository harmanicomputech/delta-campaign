<?php

declare(strict_types=1);

namespace App\Enums;

/** Data-subject request lifecycle. */
enum DsrStatus: string
{
    case Received = 'received';
    case Verifying = 'verifying';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Received => 'Received',
            self::Verifying => 'Verifying identity',
            self::InProgress => 'In progress',
            self::Completed => 'Completed',
            self::Rejected => 'Rejected',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Received => 'info',
            self::Verifying => 'warning',
            self::InProgress => 'brand',
            self::Completed => 'success',
            self::Rejected => 'neutral',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_combine(array_column(self::cases(), 'value'), array_map(fn (self $c): string => $c->label(), self::cases()));
    }
}
