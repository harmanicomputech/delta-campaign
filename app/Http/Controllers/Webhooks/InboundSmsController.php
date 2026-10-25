<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessInboundSms;
use App\Models\InboundSms;
use App\Support\Crypto\PhoneHasher;
use App\Support\Phone\NigerianPhoneNumber;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

/**
 * Africa's Talking inbound SMS callback (from, to, text, date, id, linkId).
 * Stores the message (hashed sender, encrypted text) and queues processing;
 * the provider always gets a quick 200 so it does not retry needlessly.
 */
final class InboundSmsController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $from = NigerianPhoneNumber::normalise($request->string('from')->toString());
        $text = mb_substr($request->string('text')->toString(), 0, 1000);

        if ($from === null || trim($text) === '') {
            return response('', 200);
        }

        $providerId = $request->string('id')->toString() ?: null;
        $attributes = [
            'phone_hash' => PhoneHasher::hash($from),
            'phone_last4' => substr($from, -4),
            'text_encrypted' => $text,
            'status' => 'received',
            'received_at' => $this->receivedAt($request->string('date')->toString()),
        ];

        $sms = $providerId !== null
            ? InboundSms::query()->firstOrCreate(['provider' => 'africastalking', 'provider_id' => $providerId], $attributes)
            : InboundSms::query()->create(['provider' => 'africastalking'] + $attributes);

        if ($sms->wasRecentlyCreated) {
            ProcessInboundSms::dispatch($sms->id, $from);
        }

        return response('', 200);
    }

    private function receivedAt(string $date): Carbon
    {
        try {
            $parsed = $date !== '' ? Carbon::parse($date) : now();
        } catch (\Throwable) {
            $parsed = now();
        }

        return $parsed->isFuture() ? now() : $parsed;
    }
}
