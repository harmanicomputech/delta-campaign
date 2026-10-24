<?php

declare(strict_types=1);

namespace App\Enums;

/** Volunteer lifecycle. */
enum VolunteerStatus: string
{
    case Applied = 'applied';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Suspended = 'suspended';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Applied => 'Applied',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Suspended => 'Suspended',
            self::Inactive => 'Inactive',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Applied => 'info',
            self::Approved => 'success',
            self::Rejected => 'neutral',
            self::Suspended => 'danger',
            self::Inactive => 'neutral',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_combine(array_column(self::cases(), 'value'), array_map(fn (self $c): string => $c->label(), self::cases()));
    }
}
