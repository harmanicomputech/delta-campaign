<?php

declare(strict_types=1);

namespace App\Enums;

/** Fact-check findings. */
enum Verdict: string
{
    case False = 'false';
    case Misleading = 'misleading';
    case MissingContext = 'missing_context';
    case True = 'true';
    case Unverifiable = 'unverifiable';

    public function label(): string
    {
        return match ($this) {
            self::False => 'False',
            self::Misleading => 'Misleading',
            self::MissingContext => 'Missing context',
            self::True => 'True',
            self::Unverifiable => 'Cannot be verified',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::False => 'danger',
            self::Misleading => 'warning',
            self::MissingContext => 'warning',
            self::True => 'success',
            self::Unverifiable => 'neutral',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_combine(array_column(self::cases(), 'value'), array_map(fn (self $c): string => $c->label(), self::cases()));
    }
}
