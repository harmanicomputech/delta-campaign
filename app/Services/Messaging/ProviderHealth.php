<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Enums\ConsentChannel;
use App\Models\OutboundMessage;
use Illuminate\Support\Facades\Cache;

/**
 * Per-channel provider health. After repeated consecutive failures the
 * channel "trips" for a few minutes so a broken provider is not hammered
 * (and money is not wasted); sending resumes automatically afterwards.
 */
final class ProviderHealth
{
    public const int TRIP_AFTER = 10;

    public const int TRIP_SECONDS = 300;

    public function success(ConsentChannel $channel): void
    {
        Cache::forget($this->key($channel, 'consecutive'));
    }

    public function failure(ConsentChannel $channel, string $error): void
    {
        $failures = (int) Cache::increment($this->key($channel, 'consecutive'));
        Cache::put($this->key($channel, 'last_error'), mb_substr($error, 0, 120), now()->addDay());

        if ($failures >= self::TRIP_AFTER) {
            Cache::put($this->key($channel, 'tripped_until'), now()->addSeconds(self::TRIP_SECONDS)->getTimestamp(), self::TRIP_SECONDS);
            Cache::forget($this->key($channel, 'consecutive'));
        }
    }

    /** Seconds until sending may resume, or 0 when the channel is healthy. */
    public function trippedFor(ConsentChannel $channel): int
    {
        return max(0, (int) Cache::get($this->key($channel, 'tripped_until'), 0) - now()->getTimestamp());
    }

    /** @return array{status: string, sent: int, delivered: int, failed: int, failure_rate: float, last_error: string|null, tripped_for: int} */
    public function summary(ConsentChannel $channel): array
    {
        $row = OutboundMessage::query()->toBase()->where('channel', $channel->value)->where('updated_at', '>', now()->subHour())
            ->selectRaw("count(*) filter (where status in ('sent','delivered')) as sent, count(*) filter (where status = 'delivered') as delivered, count(*) filter (where status = 'failed') as failed")
            ->first();

        $sent = (int) ($row->sent ?? 0);
        $failed = (int) ($row->failed ?? 0);
        $rate = $sent + $failed > 0 ? round($failed / ($sent + $failed) * 100, 1) : 0.0;
        $tripped = $this->trippedFor($channel);

        return [
            'status' => $tripped > 0 ? 'down' : ($rate >= 20 ? 'degraded' : ($sent + $failed === 0 ? 'idle' : 'healthy')),
            'sent' => $sent,
            'delivered' => (int) ($row->delivered ?? 0),
            'failed' => $failed,
            'failure_rate' => $rate,
            'last_error' => Cache::get($this->key($channel, 'last_error')),
            'tripped_for' => $tripped,
        ];
    }

    private function key(ConsentChannel $channel, string $name): string
    {
        return "provider-health:{$channel->value}:{$name}";
    }
}
