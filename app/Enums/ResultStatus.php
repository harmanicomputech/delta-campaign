<?php

declare(strict_types=1);

namespace App\Enums;

/** Review state of a campaign result submission. Never an official result. */
enum ResultStatus: string
{
    case Submitted = 'submitted';
    case Verified = 'verified';
    case Flagged = 'flagged';
    case Rejected = 'rejected';
    case Superseded = 'superseded';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Submitted (unverified)',
            self::Verified => 'Verified by campaign',
            self::Flagged => 'Flagged for review',
            self::Rejected => 'Rejected',
            self::Superseded => 'Superseded',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Submitted => 'info',
            self::Verified => 'success',
            self::Flagged => 'warning',
            self::Rejected => 'danger',
            self::Superseded => 'neutral',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_combine(array_column(self::cases(), 'value'), array_map(fn (self $c): string => $c->label(), self::cases()));
    }
}
