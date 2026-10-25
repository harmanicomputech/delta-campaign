<?php

declare(strict_types=1);

namespace App\Enums;

/** How a public project record was checked before publication. */
enum ProjectVerification: string
{
    case Unverified = 'unverified';
    case Documents = 'documents';
    case Site = 'site';

    public function label(): string
    {
        return match ($this) {
            self::Unverified => 'Not yet verified',
            self::Documents => 'Documents checked',
            self::Site => 'Site visited',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Unverified => 'neutral',
            self::Documents => 'info',
            self::Site => 'success',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_combine(array_column(self::cases(), 'value'), array_map(fn (self $c): string => $c->label(), self::cases()));
    }
}
