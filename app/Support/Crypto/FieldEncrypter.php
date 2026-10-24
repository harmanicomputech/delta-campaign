<?php

declare(strict_types=1);

namespace App\Support\Crypto;

use Illuminate\Encryption\Encrypter;

/** AES-256-GCM encryption for personal-data columns, with its own key. */
final class FieldEncrypter
{
    private static ?Encrypter $encrypter = null;

    private static ?string $loadedKey = null;

    public static function instance(): Encrypter
    {
        $key = Keys::fieldEncryption();

        if (self::$encrypter === null || self::$loadedKey !== $key) {
            self::$encrypter = new Encrypter($key, 'aes-256-gcm');
            self::$loadedKey = $key;
        }

        return self::$encrypter;
    }

    public static function encrypt(string $value): string
    {
        return self::instance()->encryptString($value);
    }

    public static function decrypt(string $payload): string
    {
        return self::instance()->decryptString($payload);
    }
}
