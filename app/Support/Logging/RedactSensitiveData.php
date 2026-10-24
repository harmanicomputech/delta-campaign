<?php

declare(strict_types=1);

namespace App\Support\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Removes secrets and personal data from log records before they are written.
 *
 * - Context/extra values whose key looks sensitive are replaced with "[REDACTED]".
 * - Nigerian phone numbers anywhere in the message or string values are masked,
 *   keeping only the last four digits for operational correlation.
 */
final class RedactSensitiveData implements ProcessorInterface
{
    public const string REDACTED = '[REDACTED]';

    /** Key fragments that mark a value as sensitive (matched case-insensitively). */
    private const array SENSITIVE_KEYS = [
        'password', 'passwd', 'secret', 'token', 'otp', 'pin', 'api_key', 'apikey',
        'authorization', 'cookie', 'phone', 'msisdn', 'mobile', 'totp', 'recovery_code',
        'session', 'credential', 'private_key',
    ];

    /** +234 / 234 / 0 followed by a Nigerian mobile prefix and the remaining digits. */
    private const string PHONE_PATTERN = '/(?<![\d+])(?:\+?234|0)([789][01]\d)[\s-]?(\d{3})[\s-]?(\d{4})(?!\d)/';

    private const int MAX_DEPTH = 6;

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(
            message: self::maskPhoneNumbers($record->message),
            context: $this->redact($record->context),
            extra: $this->redact($record->extra),
        );
    }

    public static function maskPhoneNumbers(string $value): string
    {
        return (string) preg_replace_callback(
            self::PHONE_PATTERN,
            static fn (array $match): string => '+234******'.$match[3],
            $value,
        );
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    private function redact(array $data, int $depth = 0): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && $this->isSensitiveKey($key)) {
                $data[$key] = self::REDACTED;

                continue;
            }

            if (is_array($value)) {
                $data[$key] = $depth >= self::MAX_DEPTH ? self::REDACTED : $this->redact($value, $depth + 1);
            } elseif (is_string($value)) {
                $data[$key] = self::maskPhoneNumbers($value);
            }
        }

        return $data;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalised = strtolower($key);

        // Avoid false positives such as "opinion", "spinner" or "shipping" for the short "pin"/"otp" fragments.
        foreach (self::SENSITIVE_KEYS as $fragment) {
            if (strlen($fragment) <= 3) {
                if (preg_match('/(^|[_\-.])'.$fragment.'($|[_\-.])/', $normalised) === 1) {
                    return true;
                }

                continue;
            }

            if (str_contains($normalised, $fragment)) {
                return true;
            }
        }

        return false;
    }
}
