<?php

declare(strict_types=1);

namespace App\Enums;

/** Rapid-response case workflow; publication always goes through the CMS. */
enum CaseStatus: string
{
    case New = 'new';
    case Investigating = 'investigating';
    case LegalReview = 'legal_review';
    case AwaitingApproval = 'awaiting_approval';
    case Approved = 'approved';
    case Closed = 'closed';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Investigating => 'Investigating',
            self::LegalReview => 'Legal review',
            self::AwaitingApproval => 'Awaiting approval',
            self::Approved => 'Approved',
            self::Closed => 'Closed',
            self::Rejected => 'No response',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::New => 'info',
            self::Investigating => 'brand',
            self::LegalReview => 'warning',
            self::AwaitingApproval => 'warning',
            self::Approved => 'success',
            self::Closed => 'neutral',
            self::Rejected => 'neutral',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_combine(array_column(self::cases(), 'value'), array_map(fn (self $c): string => $c->label(), self::cases()));
    }
}
