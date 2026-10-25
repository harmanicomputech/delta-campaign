<?php

declare(strict_types=1);

namespace App\Enums;

/** Incident handling workflow. */
enum IncidentStatus: string
{
    case Reported = 'reported';
    case Acknowledged = 'acknowledged';
    case Escalated = 'escalated';
    case Resolved = 'resolved';
    case Dismissed = 'dismissed';

    public function label(): string
    {
        return match ($this) {
            self::Reported => 'Reported',
            self::Acknowledged => 'Acknowledged',
            self::Escalated => 'Escalated',
            self::Resolved => 'Resolved',
            self::Dismissed => 'Dismissed',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Reported => 'info',
            self::Acknowledged => 'brand',
            self::Escalated => 'danger',
            self::Resolved => 'success',
            self::Dismissed => 'neutral',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_combine(array_column(self::cases(), 'value'), array_map(fn (self $c): string => $c->label(), self::cases()));
    }
}
