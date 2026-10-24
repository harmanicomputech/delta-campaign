<?php

declare(strict_types=1);

namespace App\Enums;

/** How a contact attempt was made. */
enum ContactChannel: string
{
    case Door = 'door';
    case Phone = 'phone';
    case Sms = 'sms';
    case Event = 'event';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Door => 'Door-to-door',
            self::Phone => 'Phone call',
            self::Sms => 'SMS',
            self::Event => 'Event',
            self::Other => 'Other',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_combine(array_column(self::cases(), 'value'), array_map(fn (self $c): string => $c->label(), self::cases()));
    }
}
