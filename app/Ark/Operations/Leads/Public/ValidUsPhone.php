<?php

namespace App\Ark\Operations\Leads\Public;

use App\Ark\Operations\PhoneNumber;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class ValidUsPhone implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $normalized = PhoneNumber::normalize(is_string($value) ? $value : null);

        if ($normalized === null || strlen($normalized) !== 10) {
            $fail('Enter a valid 10-digit phone number so we can reach you.');
        }
    }
}
