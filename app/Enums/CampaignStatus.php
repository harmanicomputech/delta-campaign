<?php

declare(strict_types=1);

namespace App\Enums;

/** Message campaign lifecycle: draft → pending_approval → approved/scheduled → sending → completed. */
enum CampaignStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Rejected = 'rejected';
    case Scheduled = 'scheduled';
    case Sending = 'sending';
    case Paused = 'paused';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PendingApproval => 'Awaiting approval',
            self::Rejected => 'Rejected',
            self::Scheduled => 'Scheduled',
            self::Sending => 'Sending',
            self::Paused => 'Paused',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::PendingApproval => 'warning',
            self::Rejected => 'danger',
            self::Scheduled => 'info',
            self::Sending => 'brand',
            self::Paused => 'warning',
            self::Completed => 'success',
            self::Cancelled => 'neutral',
        };
    }

    public function isEditable(): bool
    {
        return $this === self::Draft || $this === self::Rejected;
    }

    public function isFinished(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled], true);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_combine(array_column(self::cases(), 'value'), array_map(fn (self $c): string => $c->label(), self::cases()));
    }
}
