<?php

declare(strict_types=1);

namespace App\Enums;

/** Task history entries. */
enum TaskEventType: string
{
    case Created = 'created';
    case Assigned = 'assigned';
    case StatusChanged = 'status_changed';
    case Commented = 'commented';
    case EvidenceAdded = 'evidence_added';
    case Escalated = 'escalated';
    case Reminded = 'reminded';
    case DueChanged = 'due_changed';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Created',
            self::Assigned => 'Assigned',
            self::StatusChanged => 'Status changed',
            self::Commented => 'Comment',
            self::EvidenceAdded => 'Evidence added',
            self::Escalated => 'Escalated',
            self::Reminded => 'Reminder sent',
            self::DueChanged => 'Due date changed',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_combine(array_column(self::cases(), 'value'), array_map(fn (self $c): string => $c->label(), self::cases()));
    }
}
