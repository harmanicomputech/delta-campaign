<?php

declare(strict_types=1);

namespace App\Enums;

/** Geographic level of a role assignment, narrowest last. */
enum ScopeType: string
{
    case State = 'state';
    case District = 'district';
    case Lga = 'lga';
    case Ward = 'ward';
    case PollingUnit = 'polling_unit';

    public function label(): string
    {
        return match ($this) {
            self::State => 'Statewide',
            self::District => 'Senatorial district',
            self::Lga => 'LGA',
            self::Ward => 'Ward',
            self::PollingUnit => 'Polling unit',
        };
    }

    /** Breadth rank: lower is broader. */
    public function rank(): int
    {
        return match ($this) {
            self::State => 0,
            self::District => 1,
            self::Lga => 2,
            self::Ward => 3,
            self::PollingUnit => 4,
        };
    }
}
