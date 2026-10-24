<?php

declare(strict_types=1);

namespace App\Services\Sms\Contracts;

use App\Services\Sms\SmsResult;

/** Outbound SMS provider. Implementations must never log message bodies or full numbers. */
interface SmsGateway
{
    public function send(string $toE164, string $message): SmsResult;

    /** Short provider identifier stored with message records, e.g. "africastalking". */
    public function name(): string;
}
