<?php

namespace App\Ark\Operations\RepairOrders;

final class EstimateCompanionTokens
{
    /** @var list<string> */
    private const STOP = [
        'a', 'an', 'the', 'and', 'or', 'of', 'for', 'to', 'with', 'on', 'in', 'at',
        'replace', 'replacement', 'remove', 'perform', 'labor', 'hours', 'hour',
        'qty', 'job', 'rnr', 'kit', 'customer', 'provided', 'new', 'oem', 'aftermarket',
    ];

    /** @var list<string> */
    private const PREFERRED_LABELS = [
        'oil', 'coolant', 'antifreeze', 'fluid', 'atf', 'filter', 'gasket', 'belt',
        'pump', 'seal', 'grease', 'lubricant',
    ];

    /**
     * @return list<string>
     */
    public static function from(string $text): array
    {
        $normalized = mb_strtolower(trim($text));
        $normalized = str_replace(['w-', 'w –', 'w—'], 'w', $normalized);
        $parts = preg_split('/[^a-z0-9]+/u', $normalized) ?: [];
        $tokens = [];

        foreach ($parts as $part) {
            if ($part === '' || in_array($part, self::STOP, true)) {
                continue;
            }

            if (strlen($part) < 3 && ! preg_match('/^\d+w\d+$/', $part)) {
                continue;
            }

            $tokens[] = $part;
        }

        $tokens = array_values(array_unique($tokens));
        sort($tokens);

        return array_slice($tokens, 0, 3);
    }

    /**
     * @param  list<string>  $tokens
     */
    public static function labelFor(string $text, array $tokens): string
    {
        $haystack = mb_strtolower($text);

        foreach (self::PREFERRED_LABELS as $label) {
            if (str_contains($haystack, $label)) {
                return $label;
            }
        }

        if (preg_match('/\b(\d+\s*w-?\s*\d+)\b/u', $haystack, $match)) {
            return strtolower(str_replace(' ', '', $match[1]));
        }

        return $tokens[0] ?? 'item';
    }

    public static function key(array $tokens): string
    {
        return implode('|', $tokens);
    }

    public static function haystack(RepairOrder $repairOrder): string
    {
        $parts = [(string) $repairOrder->concern_summary];

        foreach ($repairOrder->concerns as $concern) {
            $parts[] = (string) $concern->summary;
            $parts[] = (string) $concern->recommendation;
        }

        foreach ($repairOrder->lines as $line) {
            $parts[] = (string) $line->description;
            $parts[] = (string) $line->customer_description;
        }

        return mb_strtolower(implode(' ', $parts));
    }

    public static function lineText(RepairOrderLine $line): string
    {
        return mb_strtolower(trim(implode(' ', array_filter([
            (string) $line->description,
            (string) $line->customer_description,
        ]))));
    }

    public static function containsPhrase(string $haystack, string $needle): bool
    {
        $needle = mb_strtolower(trim($needle));

        if ($needle === '') {
            return false;
        }

        return str_contains($haystack, $needle);
    }

    /**
     * @param  list<string>  $tokens
     */
    public static function containsTokens(string $haystack, array $tokens): bool
    {
        if ($tokens === []) {
            return false;
        }

        foreach ($tokens as $token) {
            if (! preg_match('/\b'.preg_quote($token, '/').'\b/u', $haystack)) {
                return false;
            }
        }

        return true;
    }
}
