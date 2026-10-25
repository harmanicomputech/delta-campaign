<?php

declare(strict_types=1);

namespace Tests\Doubles;

use App\Services\Sms\Contracts\SmsGateway;
use App\Services\Sms\SmsResult;

/** SMS gateway that returns scripted results, for failure-handling tests. */
final class ScriptedSmsGateway implements SmsGateway
{
    /** @var list<array{to: string, message: string}> */
    public array $sent = [];

    /** @param list<SmsResult> $results returned in order; the last one repeats */
    public function __construct(private array $results) {}

    public function send(string $toE164, string $message): SmsResult
    {
        $this->sent[] = ['to' => $toE164, 'message' => $message];

        return count($this->results) > 1 ? array_shift($this->results) : $this->results[0];
    }

    public function name(): string
    {
        return 'scripted';
    }
}
