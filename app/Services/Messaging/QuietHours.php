<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Services\Settings\Settings;
use Illuminate\Support\Carbon;

/** No campaign messages during quiet hours (local time, e.g. 21:00–08:00). */
final class QuietHours
{
    public function __construct(private readonly Settings $settings) {}

    /** Seconds until sending may start, or 0 when outside quiet hours. */
    public function secondsUntilOpen(?Carbon $at = null): int
    {
        $tz = (string) config('campaign.display_timezone');
        $now = ($at ?? now())->copy()->timezone($tz);
        $start = (int) $this->settings->get('messaging.quiet_start');
        $end = (int) $this->settings->get('messaging.quiet_end');

        if ($start === $end) {
            return 0;
        }

        $hour = (int) $now->format('G');
        $quiet = $start > $end ? ($hour >= $start || $hour < $end) : ($hour >= $start && $hour < $end);

        if (! $quiet) {
            return 0;
        }

        $open = $now->copy()->setTime($end, 0);
        if ($open->lte($now)) {
            $open->addDay();
        }

        return (int) $now->diffInSeconds($open);
    }
}
