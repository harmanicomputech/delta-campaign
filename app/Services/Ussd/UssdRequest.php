<?php

declare(strict_types=1);

namespace App\Services\Ussd;

/** A provider-neutral USSD callback: session, caller (E.164) and the cumulative input ("1*2*Ada"). */
final readonly class UssdRequest
{
    public function __construct(
        public string $sessionId,
        public string $phone,
        public string $text,
        public ?string $serviceCode = null,
    ) {}
}
