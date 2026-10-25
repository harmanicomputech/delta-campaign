<?php

declare(strict_types=1);

namespace App\Services\Messaging\Drivers;

use App\Services\Messaging\Contracts\WhatsAppGateway;
use App\Services\Sms\SmsResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * WhatsApp Business Platform (Cloud API) template messages.
 *
 * @see https://developers.facebook.com/docs/whatsapp/cloud-api/guides/send-message-templates
 */
final class CloudApiWhatsAppGateway implements WhatsAppGateway
{
    /** Error codes after which retrying cannot succeed (undeliverable, outside window, template issues). */
    private const array PERMANENT = [131026, 131047, 131051, 132000, 132001, 132005, 132007, 132012, 132015, 132016];

    public function __construct(private readonly string $phoneNumberId, private readonly string $token, private readonly string $version = 'v20.0', private readonly int $timeout = 10) {}

    public function sendTemplate(string $toE164, string $template, string $language, array $parameters): SmsResult
    {
        $components = $parameters === [] ? [] : [[
            'type' => 'body',
            'parameters' => array_map(fn (string $text): array => ['type' => 'text', 'text' => $text], $parameters),
        ]];

        try {
            $response = Http::withToken($this->token)->acceptJson()->timeout($this->timeout)
                ->post("https://graph.facebook.com/{$this->version}/{$this->phoneNumberId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'to' => ltrim($toE164, '+'),
                    'type' => 'template',
                    'template' => array_filter(['name' => $template, 'language' => ['code' => $language === 'pcm' ? 'en' : $language], 'components' => $components]),
                ]);
        } catch (ConnectionException) {
            return SmsResult::failed('ConnectionError', permanent: false);
        }

        if ($response->successful() && ($id = $response->json('messages.0.id'))) {
            return SmsResult::accepted((string) $id, 'accepted');
        }

        $code = (int) $response->json('error.code');
        if ($response->status() === 401) {
            Log::error('WhatsApp Cloud API rejected the access token.');
        }

        return SmsResult::failed('WhatsApp'.($code ?: $response->status()), in_array($code, self::PERMANENT, true) || $response->status() === 400);
    }

    public function name(): string
    {
        return 'whatsapp_cloud';
    }
}
