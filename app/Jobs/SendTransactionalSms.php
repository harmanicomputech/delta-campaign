<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Sms\Contracts\SmsGateway;
use App\Support\Crypto\FieldEncrypter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Sends a transactional SMS (one-time codes, security alerts). These are
 * requested by, or protect, the recipient, so marketing consent does not apply.
 * The recipient and body are encrypted inside the queued payload.
 */
final class SendTransactionalSms implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [5, 20];

    public int $timeout = 30;

    private readonly string $sealed;

    public function __construct(string $toE164, string $message, public readonly string $kind)
    {
        $this->sealed = FieldEncrypter::encrypt(json_encode(['to' => $toE164, 'message' => $message], JSON_THROW_ON_ERROR));
        $this->onQueue('otp');
    }

    public function handle(SmsGateway $gateway): void
    {
        ['to' => $to, 'message' => $message] = json_decode(FieldEncrypter::decrypt($this->sealed), true, flags: JSON_THROW_ON_ERROR);

        $result = $gateway->send($to, $message);

        if ($result->accepted) {
            return;
        }

        Log::warning('Transactional SMS not accepted', ['kind' => $this->kind, 'status' => $result->status, 'last4' => substr($to, -4)]);

        if (! $result->permanentFailure) {
            throw new RuntimeException("SMS provider did not accept the message ({$result->status}).");
        }
    }
}
