<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\Phone\NigerianPhoneNumber;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class NigerianMobileNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || NigerianPhoneNumber::normalise($value) === null) {
            $fail('validation.nigerian_mobile')->translate();
        }
    }
}
