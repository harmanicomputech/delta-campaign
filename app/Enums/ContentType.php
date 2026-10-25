<?php

declare(strict_types=1);

namespace App\Enums;

/** Kinds of CMS content. */
enum ContentType: string
{
    case Page = 'page';
    case News = 'news';
    case FactCheck = 'fact_check';

    public function label(): string
    {
        return match ($this) {
            self::Page => 'Page',
            self::News => 'News',
            self::FactCheck => 'Fact-check',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Page => 'neutral',
            self::News => 'info',
            self::FactCheck => 'accent',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_combine(array_column(self::cases(), 'value'), array_map(fn (self $c): string => $c->label(), self::cases()));
    }
}
