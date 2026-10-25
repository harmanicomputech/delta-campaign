<?php

declare(strict_types=1);

namespace App\Services\Messaging\Contracts;

use App\Services\Sms\SmsResult;

/**
 * WhatsApp Business messaging. Campaign messages are business-initiated, so only
 * provider-approved templates may be used; free text is not offered.
 */
interface WhatsAppGateway
{
    /** @param list<string> $parameters body parameters in template order */
    public function sendTemplate(string $toE164, string $template, string $language, array $parameters): SmsResult;

    public function name(): string;
}
