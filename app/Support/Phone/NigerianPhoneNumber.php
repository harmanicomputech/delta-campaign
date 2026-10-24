<?php

declare(strict_types=1);

namespace App\Support\Phone;

/**
 * Normalises Nigerian mobile numbers to E.164 (+234XXXXXXXXXX).
 *
 * Accepts the common written forms: 0803 000 0000, 803-000-0000, 2348030000000,
 * +234 803 000 0000 and 002348030000000. Only mobile ranges (7xx, 8xx, 9xx with a
 * 0/1 second digit) are accepted; landlines are rejected.
 */
final class NigerianPhoneNumber
{
    private const string MOBILE_SUBSCRIBER = '/^[789][01]\d{8}$/';

    public static function normalise(string $input): ?string
    {
        $digits = preg_replace('/\D+/', '', $input) ?? '';

        $national = match (true) {
            str_starts_with($digits, '00234') => substr($digits, 5),
            str_starts_with($digits, '234') => substr($digits, 3),
            str_starts_with($digits, '0') => substr($digits, 1),
            default => $digits,
        };

        // A trunk "0" sometimes survives after the country code: +234 0803…
        if (strlen($national) === 11 && $national[0] === '0') {
            $national = substr($national, 1);
        }

        return preg_match(self::MOBILE_SUBSCRIBER, $national) === 1 ? '+234'.$national : null;
    }

    /** Display form that reveals only the last four digits, e.g. +234 ••• ••• 1234. */
    public static function mask(string $e164): string
    {
        return '+234 ••• ••• '.substr($e164, -4);
    }
}
