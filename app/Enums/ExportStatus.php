<?php

declare(strict_types=1);

namespace App\Enums;

/** Export job lifecycle. */
enum ExportStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Queued',
            self::Running => 'Generating',
            self::Completed => 'Ready',
            self::Failed => 'Failed',
            self::Expired => 'Expired',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Queued => 'info',
            self::Running => 'info',
            self::Completed => 'success',
            self::Failed => 'danger',
            self::Expired => 'neutral',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_combine(array_column(self::cases(), 'value'), array_map(fn (self $c): string => $c->label(), self::cases()));
    }
}
