<?php

declare(strict_types=1);

namespace App\Enums;

/** Where a supporter record came from. */
enum SupporterSource: string
{
    case Web = 'web';
    case Ussd = 'ussd';
    case Sms = 'sms';
    case Field = 'field';
    case Event = 'event';
    case Staff = 'staff';

    public function label(): string
    {
        return match ($this) {
            self::Web => 'Website',
            self::Ussd => 'USSD',
            self::Sms => 'SMS',
            self::Field => 'Field canvassing',
            self::Event => 'Event',
            self::Staff => 'Staff entry',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_combine(array_column(self::cases(), 'value'), array_map(fn (self $c): string => $c->label(), self::cases()));
    }
}
