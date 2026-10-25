<?php

declare(strict_types=1);

namespace App\Services\Messaging\Drivers;

use App\Services\Messaging\Contracts\WhatsAppGateway;
use App\Services\Sms\SmsResult;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/** Development driver: keeps the last 50 template sends in the cache, never contacts anyone. */
final class OutboxWhatsAppGateway implements WhatsAppGateway
{
    public const string CACHE_KEY = 'dev-whatsapp-outbox';

    public function sendTemplate(string $toE164, string $template, string $language, array $parameters): SmsResult
    {
        $id = 'wamid.'.Str::random(20);
        $messages = Cache::get(self::CACHE_KEY, []);
        array_unshift($messages, ['id' => $id, 'to' => $toE164, 'template' => $template, 'language' => $language, 'parameters' => $parameters, 'at' => now()->toIso8601String()]);
        Cache::put(self::CACHE_KEY, array_slice($messages, 0, 50), now()->addMinutes(30));

        return SmsResult::accepted($id, 'accepted', '0.00');
    }

    public function name(): string
    {
        return 'outbox';
    }

    /** @return list<array<string, mixed>> */
    public static function messages(): array
    {
        return Cache::get(self::CACHE_KEY, []);
    }
}
