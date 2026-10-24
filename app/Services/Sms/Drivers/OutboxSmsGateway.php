<?php

declare(strict_types=1);

namespace App\Services\Sms\Drivers;

use App\Services\Sms\Contracts\SmsGateway;
use App\Services\Sms\SmsResult;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Development/testing driver: never sends. Messages are kept for 30 minutes in
 * the cache for the local developer outbox (/design-system/outbox) and are never
 * written to logs. Refuses to operate in production.
 */
final class OutboxSmsGateway implements SmsGateway
{
    public const string CACHE_KEY = 'dev-sms-outbox';

    public function send(string $toE164, string $message): SmsResult
    {
        if (app()->isProduction()) {
            return SmsResult::failed('OutboxDriverDisabledInProduction', permanent: true);
        }

        $id = 'outbox-'.Str::lower(Str::random(12));
        $messages = Cache::get(self::CACHE_KEY, []);
        array_unshift($messages, ['id' => $id, 'to' => $toE164, 'message' => $message, 'at' => now()->toIso8601String()]);
        Cache::put(self::CACHE_KEY, array_slice($messages, 0, 50), now()->addMinutes(30));

        return SmsResult::accepted($id, 'Success', '0.00');
    }

    public function name(): string
    {
        return 'outbox';
    }

    /** @return list<array{id: string, to: string, message: string, at: string}> */
    public static function messages(): array
    {
        return Cache::get(self::CACHE_KEY, []);
    }

    public static function clear(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
