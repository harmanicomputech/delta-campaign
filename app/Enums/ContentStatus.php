<?php

declare(strict_types=1);

namespace App\Enums;

/** CMS workflow: draft → in review → approved → scheduled/published → withdrawn → archived. */
enum ContentStatus: string
{
    case Draft = 'draft';
    case InReview = 'in_review';
    case Rejected = 'rejected';
    case Approved = 'approved';
    case Scheduled = 'scheduled';
    case Published = 'published';
    case Withdrawn = 'withdrawn';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::InReview => 'In review',
            self::Rejected => 'Changes requested',
            self::Approved => 'Approved',
            self::Scheduled => 'Scheduled',
            self::Published => 'Published',
            self::Withdrawn => 'Withdrawn',
            self::Archived => 'Archived',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::InReview => 'warning',
            self::Rejected => 'danger',
            self::Approved => 'info',
            self::Scheduled => 'info',
            self::Published => 'success',
            self::Withdrawn => 'warning',
            self::Archived => 'neutral',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_combine(array_column(self::cases(), 'value'), array_map(fn (self $c): string => $c->label(), self::cases()));
    }
}
