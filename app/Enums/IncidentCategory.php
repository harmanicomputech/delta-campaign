<?php

declare(strict_types=1);

namespace App\Enums;

/** Election-day incident categories. */
enum IncidentCategory: string
{
    case Violence = 'violence';
    case Intimidation = 'intimidation';
    case VoteBuying = 'vote_buying';
    case Materials = 'materials';
    case Technology = 'technology';
    case LateOpening = 'late_opening';
    case Procedure = 'procedure';
    case Access = 'access';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Violence => 'Violence or threat',
            self::Intimidation => 'Voter intimidation',
            self::VoteBuying => 'Vote buying',
            self::Materials => 'Missing or late materials',
            self::Technology => 'BVAS or technology failure',
            self::LateOpening => 'Late opening',
            self::Procedure => 'Procedural irregularity',
            self::Access => 'Accessibility problem',
            self::Other => 'Other',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Violence => 'danger',
            self::Intimidation => 'danger',
            self::VoteBuying => 'danger',
            self::Materials => 'warning',
            self::Technology => 'warning',
            self::LateOpening => 'warning',
            self::Procedure => 'warning',
            self::Access => 'info',
            self::Other => 'neutral',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_combine(array_column(self::cases(), 'value'), array_map(fn (self $c): string => $c->label(), self::cases()));
    }
}
