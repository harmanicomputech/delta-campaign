<?php

declare(strict_types=1);

namespace App\Enums;

/** Purposes consent is given for. */
enum ConsentPurpose: string
{
    case CampaignUpdates = 'campaign_updates';
    case Volunteering = 'volunteering';
    case Events = 'events';

    public function label(): string
    {
        return match ($this) {
            self::CampaignUpdates => 'Campaign updates',
            self::Volunteering => 'Volunteer coordination',
            self::Events => 'Event information',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_combine(array_column(self::cases(), 'value'), array_map(fn (self $c): string => $c->label(), self::cases()));
    }
}
