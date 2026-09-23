<?php

namespace App\Ark\Operations\Parts;

final class PartsTechQuoteLineAttributes
{
    /**
     * @param  array<string, mixed>  $item
     */
    public static function positionLabel(array $item): ?string
    {
        $attributes = data_get($item, 'builtItem.product.attributes');

        if (! is_array($attributes)) {
            return null;
        }

        foreach ($attributes as $attribute) {
            if (! is_array($attribute)) {
                continue;
            }

            if (strcasecmp(trim((string) ($attribute['name'] ?? '')), 'Position') !== 0) {
                continue;
            }

            $value = self::firstAttributeValue($attribute['value'] ?? null);

            return $value !== '' ? $value : null;
        }

        return null;
    }

    public static function labeledDescription(string $description, ?string $positionLabel): string
    {
        if ($positionLabel === null || $positionLabel === '') {
            return $description;
        }

        if (self::descriptionContainsPosition($description, $positionLabel)) {
            return $description;
        }

        return $positionLabel.' - '.$description;
    }

    private static function descriptionContainsPosition(string $description, string $positionLabel): bool
    {
        $normalizedDescription = strtolower($description);
        $normalizedPosition = strtolower($positionLabel);

        if (str_contains($normalizedDescription, $normalizedPosition)) {
            return true;
        }

        foreach (self::positionTokens($positionLabel) as $token) {
            if (str_contains($normalizedDescription, $token)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private static function positionTokens(string $positionLabel): array
    {
        $tokens = [];

        foreach (['front', 'rear', 'left', 'right'] as $token) {
            if (str_contains(strtolower($positionLabel), $token)) {
                $tokens[] = $token;
            }
        }

        return $tokens;
    }

    private static function firstAttributeValue(mixed $value): string
    {
        if (is_array($value)) {
            foreach ($value as $entry) {
                $normalized = trim((string) $entry);

                if ($normalized !== '') {
                    return $normalized;
                }
            }

            return '';
        }

        return trim((string) $value);
    }
}
