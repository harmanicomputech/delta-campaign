<?php

declare(strict_types=1);

namespace App\Enums;

/** Skills volunteers can offer. */
enum VolunteerSkill: string
{
    case Canvassing = 'canvassing';
    case Phone = 'phone';
    case Events = 'events';
    case Transport = 'transport';
    case Media = 'media';
    case Data = 'data';
    case Language = 'language';
    case Agent = 'agent';

    public function label(): string
    {
        return match ($this) {
            self::Canvassing => 'Door-to-door canvassing',
            self::Phone => 'Phone follow-up',
            self::Events => 'Events and logistics',
            self::Transport => 'Transport',
            self::Media => 'Photography and media',
            self::Data => 'Data entry',
            self::Language => 'Local-language interpretation',
            self::Agent => 'Polling-agent duty',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_combine(array_column(self::cases(), 'value'), array_map(fn (self $c): string => $c->label(), self::cases()));
    }
}
