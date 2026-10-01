<?php

namespace App\Ark\Vehicles;

final class VehicleText
{
    public static function clean(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (in_array(strtoupper($value), ['NOT APPLICABLE', 'N/A', 'NULL', 'UNKNOWN', 'NONE'], true)) {
            return null;
        }

        return $value;
    }

    public static function title(?string $value): ?string
    {
        $value = self::clean($value);

        if ($value === null) {
            return null;
        }

        return mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
    }

    public static function displayMake(?string $value): ?string
    {
        return self::displayLabel($value, preserveShortAcronyms: true);
    }

    public static function displayModel(?string $value): ?string
    {
        return self::displayLabel($value, allowDigitAwareCasing: true);
    }

    public static function displayTrim(?string $value): ?string
    {
        $value = self::clean($value);

        if ($value === null) {
            return null;
        }

        if (self::isAllCaps($value) && preg_match('/^[A-Z0-9-]{2,5}$/', $value) === 1) {
            return $value;
        }

        return self::displayLabel($value, allowDigitAwareCasing: true);
    }

    private static function displayLabel(
        ?string $value,
        bool $preserveShortAcronyms = false,
        bool $allowDigitAwareCasing = false,
    ): ?string {
        $value = self::clean($value);

        if ($value === null) {
            return null;
        }

        if ($preserveShortAcronyms) {
            $upper = strtoupper($value);

            if (in_array($upper, ['BMW', 'GMC', 'VW'], true)) {
                return $upper;
            }
        }

        if (! self::isAllCaps($value)) {
            return $value;
        }

        if ($allowDigitAwareCasing && preg_match('/\d/', $value) === 1) {
            $lower = mb_strtolower($value, 'UTF-8');

            return mb_strtoupper(mb_substr($lower, 0, 1)).mb_substr($lower, 1);
        }

        return mb_convert_case(mb_strtolower($value, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
    }

    private static function isAllCaps(string $value): bool
    {
        return $value === strtoupper($value) && preg_match('/[A-Z]/', $value) === 1;
    }
}
