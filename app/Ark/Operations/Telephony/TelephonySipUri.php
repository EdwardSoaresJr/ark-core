<?php

namespace App\Ark\Operations\Telephony;

use App\Ark\Operations\PhoneNumber;

final class TelephonySipUri
{
    public static function normalizeForMatch(string $value): string
    {
        return TelephonySipDestination::normalize(trim($value));
    }

    public static function dialedNumber(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (str_starts_with(strtolower($value), 'sip:')) {
            $value = substr($value, 4);
        }

        if (str_contains($value, '@')) {
            $value = explode('@', $value, 2)[0];
        }

        $value = urldecode($value);

        $normalized = PhoneNumber::normalize($value);

        if ($normalized !== null) {
            return $normalized;
        }

        $digits = PhoneNumber::digits($value);

        return $digits !== '' ? $digits : null;
    }
}
