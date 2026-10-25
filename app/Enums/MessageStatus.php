<?php

declare(strict_types=1);

namespace App\Enums;

enum MessageStatus: string
{
    case Queued = 'queued';
    case Skipped = 'skipped';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Queued',
            self::Skipped => 'Not sent',
            self::Sent => 'Sent',
            self::Delivered => 'Delivered',
            self::Failed => 'Failed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Queued => 'info',
            self::Skipped, self::Cancelled => 'neutral',
            self::Sent => 'brand',
            self::Delivered => 'success',
            self::Failed => 'danger',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Skipped, self::Delivered, self::Failed, self::Cancelled], true);
    }
}
