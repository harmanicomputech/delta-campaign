<?php

declare(strict_types=1);

namespace App\Support\Crypto;

use RuntimeException;

/** Decodes the dedicated data-protection keys from configuration. */
final class Keys
{
    public static function phoneHash(): string
    {
        return self::decode('phone_hash', 'PHONE_HASH_KEY');
    }

    public static function fieldEncryption(): string
    {
        return self::decode('field_encryption', 'FIELD_ENCRYPTION_KEY');
    }

    public static function generate(): string
    {
        return 'base64:'.base64_encode(random_bytes(32));
    }

    private static function decode(string $name, string $env): string
    {
        $value = (string) config("campaign.keys.{$name}");

        if ($value === '') {
            throw new RuntimeException("{$env} is not set. Run: php artisan campaign:keys");
        }

        $key = str_starts_with($value, 'base64:') ? base64_decode(substr($value, 7), true) : $value;

        if (! is_string($key) || strlen($key) !== 32) {
            throw new RuntimeException("{$env} must be a 32-byte key (base64: prefix).");
        }

        return $key;
    }
}
