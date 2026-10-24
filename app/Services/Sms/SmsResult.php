<?php

declare(strict_types=1);

namespace App\Services\Sms;

final readonly class SmsResult
{
    public function __construct(
        public bool $accepted,
        public ?string $providerMessageId = null,
        /** Provider status text, e.g. "Success", "InvalidPhoneNumber". */
        public ?string $status = null,
        /** Cost in Naira when the provider reports it. */
        public ?string $cost = null,
        /** True when retrying cannot succeed (invalid number, blacklisted, rejected content). */
        public bool $permanentFailure = false,
    ) {}

    public static function accepted(?string $id, ?string $status = 'Success', ?string $cost = null): self
    {
        return new self(true, $id, $status, $cost);
    }

    public static function failed(string $status, bool $permanent): self
    {
        return new self(false, null, $status, null, $permanent);
    }
}
