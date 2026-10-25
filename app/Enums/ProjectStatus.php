<?php

declare(strict_types=1);

namespace App\Enums;

/** Public project records: only approved records are published. */
enum ProjectStatus: string
{
    case Draft = 'draft';
    case InReview = 'in_review';
    case Rejected = 'rejected';
    case Published = 'published';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::InReview => 'In review',
            self::Rejected => 'Changes requested',
            self::Published => 'Published',
            self::Withdrawn => 'Withdrawn',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::InReview => 'warning',
            self::Rejected => 'danger',
            self::Published => 'success',
            self::Withdrawn => 'neutral',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_combine(array_column(self::cases(), 'value'), array_map(fn (self $c): string => $c->label(), self::cases()));
    }
}
