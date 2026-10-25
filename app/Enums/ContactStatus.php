<?php

declare(strict_types=1);

namespace App\Enums;

/** Public contact-form message handling state. */
enum ContactStatus: string
{
    case New = 'new';
    case Handled = 'handled';
    case Spam = 'spam';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Handled => 'Handled',
            self::Spam => 'Spam',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::New => 'info',
            self::Handled => 'success',
            self::Spam => 'neutral',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_combine(array_column(self::cases(), 'value'), array_map(fn (self $c): string => $c->label(), self::cases()));
    }
}
