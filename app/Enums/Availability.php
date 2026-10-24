<?php

declare(strict_types=1);

namespace App\Enums;

/** When volunteers are available. */
enum Availability: string
{
    case WeekdayDay = 'weekday_day';
    case WeekdayEvening = 'weekday_evening';
    case Weekend = 'weekend';
    case ElectionDay = 'election_day';

    public function label(): string
    {
        return match ($this) {
            self::WeekdayDay => 'Weekday daytime',
            self::WeekdayEvening => 'Weekday evenings',
            self::Weekend => 'Weekends',
            self::ElectionDay => 'Election day',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_combine(array_column(self::cases(), 'value'), array_map(fn (self $c): string => $c->label(), self::cases()));
    }
}
