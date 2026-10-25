<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\OutboundMessage;
use App\Services\Messaging\MessageDispatcher;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Sends (or skips, or defers) one message. Deferrals are releases back onto the queue, bounded by retryUntil(). */
final class SendOutboundMessage implements ShouldQueue
{
    use Queueable;

    public int $timeout = 30;

    public function __construct(public readonly int $messageId)
    {
        $this->onQueue('messaging');
    }

    public function retryUntil(): DateTimeInterface
    {
        // Quiet hours, pauses and budget holds can defer a message; after three days it is left for review.
        return now()->addDays(3);
    }

    public function handle(MessageDispatcher $dispatcher): void
    {
        $message = OutboundMessage::query()->find($this->messageId);

        if ($message === null) {
            return;
        }

        if (($delay = $dispatcher->dispatch($message)) !== null) {
            $this->release($delay);
        }
    }
}
