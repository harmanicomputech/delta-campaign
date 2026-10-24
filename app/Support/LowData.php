<?php

declare(strict_types=1);

namespace App\Support;

/** Low-data mode, chosen per device (cookie set by the browser). Pages skip background polling when on. */
final class LowData
{
    public static function enabled(): bool
    {
        return request()->cookie('low_data') === '1';
    }
}
