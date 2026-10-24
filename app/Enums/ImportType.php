<?php

declare(strict_types=1);

namespace App\Enums;

enum ImportType: string
{
    case PollingUnits = 'polling_units';

    public function label(): string
    {
        return match ($this) {
            self::PollingUnits => 'Wards & polling units',
        };
    }
}
