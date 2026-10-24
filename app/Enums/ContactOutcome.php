<?php

declare(strict_types=1);

namespace App\Enums;

/** Result of a contact attempt. Deliberately records contact status, not political opinion. */
enum ContactOutcome: string
{
    case Reached = 'reached';
    case NoAnswer = 'no_answer';
    case NotHome = 'not_home';
    case WrongNumber = 'wrong_number';
    case Refused = 'refused';
    case FollowUpRequested = 'follow_up_requested';
    case Registered = 'registered';
    case DoNotContact = 'do_not_contact';

    public function label(): string
    {
        return match ($this) {
            self::Reached => 'Reached',
            self::NoAnswer => 'No answer',
            self::NotHome => 'Not at home',
            self::WrongNumber => 'Wrong number',
            self::Refused => 'Declined to talk',
            self::FollowUpRequested => 'Asked for follow-up',
            self::Registered => 'Registered as supporter',
            self::DoNotContact => 'Asked not to be contacted',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Reached => 'success',
            self::NoAnswer => 'neutral',
            self::NotHome => 'neutral',
            self::WrongNumber => 'warning',
            self::Refused => 'neutral',
            self::FollowUpRequested => 'brand',
            self::Registered => 'success',
            self::DoNotContact => 'danger',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_combine(array_column(self::cases(), 'value'), array_map(fn (self $c): string => $c->label(), self::cases()));
    }
}
