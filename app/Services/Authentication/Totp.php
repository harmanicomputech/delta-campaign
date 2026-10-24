<?php

declare(strict_types=1);

namespace App\Services\Authentication;

/**
 * RFC 6238 time-based one-time passwords (HMAC-SHA1, 30-second steps, 6 digits),
 * compatible with standard authenticator apps.
 */
final class Totp
{
    private const string ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function generateSecret(int $bytes = 20): string
    {
        return self::base32Encode(random_bytes($bytes));
    }

    public static function code(string $secret, int $timeStep, int $digits = 6): string
    {
        $key = self::base32Decode($secret);
        $counter = pack('N*', 0, $timeStep);
        $hmac = hash_hmac('sha1', $counter, $key, true);
        $offset = ord($hmac[19]) & 0x0F;
        $value = ((ord($hmac[$offset]) & 0x7F) << 24)
            | ((ord($hmac[$offset + 1]) & 0xFF) << 16)
            | ((ord($hmac[$offset + 2]) & 0xFF) << 8)
            | (ord($hmac[$offset + 3]) & 0xFF);

        return str_pad((string) ($value % (10 ** $digits)), $digits, '0', STR_PAD_LEFT);
    }

    /**
     * Returns the matching time step (to prevent replay), or null.
     * Accepts one step of clock drift either way; steps at or before $lastUsedStep are rejected.
     */
    public static function verify(string $secret, string $code, ?int $lastUsedStep = null, ?int $timestamp = null, int $window = 1): ?int
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';

        if (preg_match('/^\d{6}$/', $code) !== 1) {
            return null;
        }

        $current = intdiv($timestamp ?? time(), 30);

        for ($step = $current - $window; $step <= $current + $window; $step++) {
            if ($lastUsedStep !== null && $step <= $lastUsedStep) {
                continue;
            }

            if (hash_equals(self::code($secret, $step), $code)) {
                return $step;
            }
        }

        return null;
    }

    public static function uri(string $secret, string $account, string $issuer): string
    {
        return sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s&algorithm=SHA1&digits=6&period=30',
            rawurlencode($issuer),
            rawurlencode($account),
            $secret,
            rawurlencode($issuer),
        );
    }

    public static function base32Encode(string $data): string
    {
        $bits = '';
        foreach (str_split($data) as $char) {
            $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }

        $output = '';
        foreach (str_split($bits, 5) as $chunk) {
            $output .= self::ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }

        return $output;
    }

    public static function base32Decode(string $data): string
    {
        $data = strtoupper((string) preg_replace('/[\s=]/', '', $data));
        $bits = '';

        foreach (str_split($data) as $char) {
            $index = strpos(self::ALPHABET, $char);
            if ($index === false) {
                throw new \InvalidArgumentException('Invalid base32 secret.');
            }
            $bits .= str_pad(decbin($index), 5, '0', STR_PAD_LEFT);
        }

        $output = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $output .= chr((int) bindec($byte));
            }
        }

        return $output;
    }
}
