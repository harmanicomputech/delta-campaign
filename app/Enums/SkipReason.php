<?php

declare(strict_types=1);

namespace App\Enums;

/** Why a message was not sent. Checked again immediately before every send. */
enum SkipReason: string
{
    case NoConsent = 'no_consent';
    case Suppressed = 'suppressed';
    case Erased = 'erased';
    case Restricted = 'restricted';
    case NoPhone = 'no_phone';
    case Duplicate = 'duplicate';

    public function label(): string
    {
        return match ($this) {
            self::NoConsent => 'No consent for this channel and purpose',
            self::Suppressed => 'Opted out or suppressed',
            self::Erased => 'Record erased',
            self::Restricted => 'Processing restricted',
            self::NoPhone => 'No phone number',
            self::Duplicate => 'Same number already in this campaign',
        };
    }
}
