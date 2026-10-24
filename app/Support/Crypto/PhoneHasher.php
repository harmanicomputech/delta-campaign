<?php

declare(strict_types=1);

namespace App\Support\Crypto;

use App\Support\Phone\NigerianPhoneNumber;
use InvalidArgumentException;

/**
 * Keyed (HMAC-SHA256) hashes of E.164 phone numbers. Used for uniqueness,
 * duplicate detection, suppression lists and look-ups, so numbers never need
 * to be stored or compared in clear. Without the key, hashes cannot be reversed
 * by enumerating the (small) Nigerian number space.
 */
final class PhoneHasher
{
    public static function hash(string $e164): string
    {
        if (preg_match('/^\+\d{8,15}$/', $e164) !== 1) {
            throw new InvalidArgumentException('Phone numbers must be hashed in E.164 form.');
        }

        return hash_hmac('sha256', $e164, Keys::phoneHash());
    }

    /** Normalises then hashes; null when the input is not a valid Nigerian mobile number. */
    public static function fromInput(string $input): ?string
    {
        $e164 = NigerianPhoneNumber::normalise($input);

        return $e164 === null ? null : self::hash($e164);
    }
}
