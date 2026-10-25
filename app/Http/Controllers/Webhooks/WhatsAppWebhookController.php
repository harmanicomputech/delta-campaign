<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Enums\MessageStatus;
use App\Enums\RequestChannel;
use App\Http\Controllers\Controller;
use App\Models\OutboundMessage;
use App\Models\Supporter;
use App\Services\Consent\ConsentService;
use App\Services\Sms\Inbound\InboundSmsProcessor;
use App\Support\Crypto\PhoneHasher;
use App\Support\Phone\NigerianPhoneNumber;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** WhatsApp Cloud API webhooks: subscription check, delivery statuses and STOP replies. */
final class WhatsAppWebhookController extends Controller
{
    public function verify(Request $request): Response
    {
        $token = (string) config('services.whatsapp.verify_token');

        abort_unless($request->query('hub_mode') === 'subscribe' && $token !== '' && hash_equals($token, (string) $request->query('hub_verify_token')), 403);

        return response((string) $request->query('hub_challenge'), 200, ['Content-Type' => 'text/plain']);
    }

    public function receive(Request $request, ConsentService $consent): Response
    {
        foreach ((array) $request->input('entry', []) as $entry) {
            foreach ((array) ($entry['changes'] ?? []) as $change) {
                $value = (array) ($change['value'] ?? []);

                foreach ((array) ($value['statuses'] ?? []) as $status) {
                    $this->status((array) $status);
                }

                foreach ((array) ($value['messages'] ?? []) as $message) {
                    $text = strtoupper(trim((string) ($message['text']['body'] ?? '')));
                    $from = NigerianPhoneNumber::normalise('+'.ltrim((string) ($message['from'] ?? ''), '+'));

                    if ($from !== null && in_array((string) strtok($text, ' '), InboundSmsProcessor::STOP, true)) {
                        $hash = PhoneHasher::hash($from);
                        $consent->withdrawAll($hash, null, RequestChannel::Whatsapp, Supporter::query()->where('phone_hash', $hash)->first(), ['channel' => 'whatsapp']);
                    }
                }
            }
        }

        return response('', 200);
    }

    /** @param array<string, mixed> $status */
    private function status(array $status): void
    {
        $message = OutboundMessage::query()->where('provider_message_id', (string) ($status['id'] ?? ''))->first();

        if ($message === null || in_array($message->status, [MessageStatus::Delivered, MessageStatus::Failed], true)) {
            return;
        }

        match ($status['status'] ?? null) {
            'delivered', 'read' => $message->forceFill(['status' => MessageStatus::Delivered, 'delivered_at' => now()])->save(),
            'failed' => $message->forceFill(['status' => MessageStatus::Failed, 'failed_at' => now(), 'last_error' => 'WhatsApp'.($status['errors'][0]['code'] ?? '')])->save(),
            default => null,
        };
    }
}
