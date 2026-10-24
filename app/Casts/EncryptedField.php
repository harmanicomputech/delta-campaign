<?php

declare(strict_types=1);

namespace App\Casts;

use App\Support\Crypto\FieldEncrypter;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Encrypts a string column with the field-encryption key (not APP_KEY).
 * Use "encrypted-field:json" for arrays.
 *
 * @implements CastsAttributes<mixed, mixed>
 */
final class EncryptedField implements CastsAttributes
{
    public function __construct(private readonly string $type = 'string') {}

    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null) {
            return null;
        }

        $plain = FieldEncrypter::decrypt((string) $value);

        return $this->type === 'json' ? json_decode($plain, true, flags: JSON_THROW_ON_ERROR) : $plain;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        return FieldEncrypter::encrypt($this->type === 'json' ? json_encode($value, JSON_THROW_ON_ERROR) : (string) $value);
    }
}
