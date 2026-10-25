<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\InboundSms;
use App\Services\Sms\Inbound\InboundSmsProcessor;
use App\Support\Crypto\FieldEncrypter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Processes one inbound SMS off the request path. The sender number travels encrypted in the payload. */
final class ProcessInboundSms implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [10, 60, 300];

    private readonly string $sealedFrom;

    public function __construct(public readonly int $inboundId, string $fromE164)
    {
        $this->sealedFrom = FieldEncrypter::encrypt($fromE164);
        $this->onQueue('sms-inbound');
    }

    public function handle(InboundSmsProcessor $processor): void
    {
        if ($sms = InboundSms::query()->find($this->inboundId)) {
            $processor->process($sms, FieldEncrypter::decrypt($this->sealedFrom));
        }
    }
}
