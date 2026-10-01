<?php

namespace App\Ark\Operations\RepairOrders;

final class ScopeEntryConceptNormalizer
{
    public const MAX_LENGTH = 255;

    public static function normalize(?string $value): string
    {
        $value = RepairOrderFreeText::normalize($value);

        if ($value === '') {
            return '';
        }

        $value = mb_strtolower($value);
        // Expansion (e.g. "/" → " and ") must happen before the hard column limit.
        $value = str_replace(['&', '/'], ' and ', $value);
        $value = preg_replace('/[^a-z0-9\s]/u', ' ', $value) ?? $value;
        $value = trim((string) preg_replace('/\s+/u', ' ', $value));

        return mb_substr($value, 0, self::MAX_LENGTH);
    }
}
