<?php

declare(strict_types=1);

namespace App\Enums;

/** Data-quality state of a field record. */
enum QualityFlag: string
{
    case Ok = 'ok';
    case SuspectDuplicate = 'suspect_duplicate';
    case NeedsReview = 'needs_review';

    public function label(): string
    {
        return match ($this) {
            self::Ok => 'OK',
            self::SuspectDuplicate => 'Possible duplicate',
            self::NeedsReview => 'Needs review',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Ok => 'success',
            self::SuspectDuplicate => 'warning',
            self::NeedsReview => 'warning',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_combine(array_column(self::cases(), 'value'), array_map(fn (self $c): string => $c->label(), self::cases()));
    }
}
