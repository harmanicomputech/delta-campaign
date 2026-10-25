<?php

declare(strict_types=1);

namespace App\Enums;

/** Where an official figure was taken from. Only INEC sources are accepted. */
enum OfficialSource: string
{
    case Irev = 'inec_irev';
    case Declaration = 'inec_declaration';
    case CertifiedCopy = 'inec_certified_copy';

    public function label(): string
    {
        return match ($this) {
            self::Irev => 'INEC IReV portal',
            self::Declaration => 'INEC declaration',
            self::CertifiedCopy => 'INEC certified true copy',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Irev => 'info',
            self::Declaration => 'info',
            self::CertifiedCopy => 'info',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_combine(array_column(self::cases(), 'value'), array_map(fn (self $c): string => $c->label(), self::cases()));
    }
}
