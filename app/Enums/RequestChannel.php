<?php

declare(strict_types=1);

namespace App\Enums;

/** How a request or consent reached us. */
enum RequestChannel: string
{
    case Web = 'web';
    case Ussd = 'ussd';
    case Sms = 'sms';
    case Field = 'field';
    case Staff = 'staff';
    case Event = 'event';

    public function label(): string
    {
        return match ($this) {
            self::Web => 'Website',
            self::Ussd => 'USSD',
            self::Sms => 'SMS',
            self::Field => 'Field',
            self::Staff => 'Staff',
            self::Event => 'Event',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_combine(array_column(self::cases(), 'value'), array_map(fn (self $c): string => $c->label(), self::cases()));
    }
}
