<?php

declare(strict_types=1);

namespace App\Services\Sms\Drivers;

use App\Services\Sms\Contracts\SmsGateway;
use App\Services\Sms\SmsResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Africa's Talking bulk SMS API (one recipient per call, so each message gets
 * its own provider id and delivery receipt).
 *
 * @see https://developers.africastalking.com/docs/sms/sending/bulk
 */
final class AfricasTalkingSmsGateway implements SmsGateway
{
    /** Statuses after which retrying cannot succeed. */
    private const array PERMANENT = ['InvalidPhoneNumber', 'UserInBlacklist', 'InvalidSenderId', 'RejectedByGateway', 'UnsupportedNumberType', 'DoNotDisturbRejection'];

    public function __construct(
        private readonly string $username,
        private readonly string $apiKey,
        private readonly ?string $senderId,
        private readonly int $timeout = 10,
    ) {}

    public function send(string $toE164, string $message): SmsResult
    {
        $payload = array_filter([
            'username' => $this->username,
            'to' => $toE164,
            'message' => $message,
            'from' => $this->senderId,
        ], fn ($value): bool => $value !== null && $value !== '');

        try {
            $response = Http::asForm()
                ->acceptJson()
                ->withHeaders(['apiKey' => $this->apiKey])
                ->timeout($this->timeout)
                ->post($this->endpoint(), $payload);
        } catch (ConnectionException) {
            return SmsResult::failed('ConnectionError', permanent: false);
        }

        if ($response->status() === 401) {
            Log::error('Africa\'s Talking rejected the API credentials.');

            return SmsResult::failed('AuthenticationFailed', permanent: false);
        }

        if (! $response->successful()) {
            return SmsResult::failed('HttpError'.$response->status(), permanent: $response->status() === 400);
        }

        $recipient = $response->json('SMSMessageData.Recipients.0');

        if (! is_array($recipient)) {
            return SmsResult::failed((string) ($response->json('SMSMessageData.Message') ?: 'NoRecipient'), permanent: true);
        }

        $status = (string) ($recipient['status'] ?? 'Unknown');

        if (in_array((int) ($recipient['statusCode'] ?? 0), [100, 101, 102], true) || $status === 'Success') {
            return SmsResult::accepted(
                (string) ($recipient['messageId'] ?? ''),
                $status,
                self::parseCost((string) ($recipient['cost'] ?? '')),
            );
        }

        return SmsResult::failed($status, in_array($status, self::PERMANENT, true));
    }

    public function name(): string
    {
        return 'africastalking';
    }

    private function endpoint(): string
    {
        return $this->username === 'sandbox'
            ? 'https://api.sandbox.africastalking.com/version1/messaging'
            : 'https://api.africastalking.com/version1/messaging';
    }

    /** "NGN 2.2000" → "2.2000". */
    private static function parseCost(string $cost): ?string
    {
        return preg_match('/([\d.]+)/', $cost, $match) === 1 ? $match[1] : null;
    }
}
