<?php

declare(strict_types=1);

namespace App\Enums;

/** Communication channels that need separate, explicit consent. */
enum ConsentChannel: string
{
    case Sms = 'sms';
    case Whatsapp = 'whatsapp';
    case Voice = 'voice';

    public function label(): string
    {
        return match ($this) {
            self::Sms => 'SMS',
            self::Whatsapp => 'WhatsApp',
            self::Voice => 'Phone calls',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_combine(array_column(self::cases(), 'value'), array_map(fn (self $c): string => $c->label(), self::cases()));
    }
}
