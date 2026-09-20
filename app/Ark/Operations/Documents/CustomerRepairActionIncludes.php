<?php

namespace App\Ark\Operations\Documents;

/**
 * Customer-facing "Includes" bullets from shop repair-action titles.
 * Presentation only — does not change repair_order_work_groups authority.
 */
final class CustomerRepairActionIncludes
{
    /**
     * @param  list<array{title?: string|null, lines?: list<mixed>}|mixed>  $workGroups
     * @return list<string>
     */
    public static function bulletsForConcern(string $concernSummary, array $workGroups): array
    {
        $bullets = [];
        $concernKey = self::normalizeKey($concernSummary);

        foreach ($workGroups as $workGroup) {
            if (! is_array($workGroup)) {
                continue;
            }

            $lines = $workGroup['lines'] ?? [];

            if (! is_array($lines) || $lines === []) {
                continue;
            }

            $title = trim((string) ($workGroup['title'] ?? ''));

            if ($title === '') {
                continue;
            }

            foreach (self::expandTitle($title) as $bullet) {
                if (self::normalizeKey($bullet) === $concernKey) {
                    continue;
                }

                if (! in_array($bullet, $bullets, true)) {
                    $bullets[] = $bullet;
                }
            }
        }

        return $bullets;
    }

    /**
     * @return list<string>
     */
    public static function expandTitle(string $title): array
    {
        $title = trim(preg_replace('/\s+/u', ' ', $title) ?? $title);

        if ($title === '') {
            return [];
        }

        $isReplace = (bool) preg_match('/(?:^|[\s,;\/-])r\s*(?:&|and)\s*r(?:$|[\s,;\/.])/iu', $title)
            || (bool) preg_match('/\br\s*\/\s*r\b/iu', $title)
            || (bool) preg_match('/\bremove\s*(?:and|&)\s*replace\b/iu', $title);

        $core = trim((string) preg_replace(
            '/(?:,?\s*)(?:r\s*(?:&|and)\s*r|r\s*\/\s*r|remove\s*(?:and|&)\s*replace)\s*\.?$/iu',
            '',
            $title,
        ));

        if ($core === '') {
            $core = $title;
        }

        $parts = preg_split('/\s*(?:&| and |\/|,)\s*/iu', $core) ?: [];
        $parts = array_values(array_filter(array_map('trim', $parts), fn (string $part): bool => $part !== ''));

        if ($parts === []) {
            return [self::sentenceCase($title)];
        }

        if (! $isReplace) {
            if (count($parts) === 1) {
                return [self::sentenceCase($parts[0])];
            }

            return [self::sentenceCase($core)];
        }

        return array_map(
            fn (string $part): string => 'Replace '.mb_strtolower($part),
            $parts,
        );
    }

    /**
     * Soft group header for a single repair action on line lists.
     * Title case — customer-readable “what we're doing,” not washed-out uppercase chrome.
     */
    public static function groupHeading(string $title): string
    {
        $bullets = self::expandTitle($title);

        if ($bullets === []) {
            return self::titleCaseWords($title);
        }

        return implode(' · ', array_map(
            fn (string $bullet): string => self::titleCaseWords($bullet),
            $bullets,
        ));
    }

    private static function sentenceCase(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        return mb_strtoupper(mb_substr($value, 0, 1)).mb_substr($value, 1);
    }

    private static function titleCaseWords(string $value): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);

        if ($value === '') {
            return '';
        }

        return collect(preg_split('/\s+/u', mb_strtolower($value)) ?: [])
            ->filter(fn (string $word): bool => $word !== '')
            ->values()
            ->map(function (string $word, int $index): string {
                if ($index > 0 && in_array($word, ['and', 'or', 'of', 'the', 'a', 'an', 'with'], true)) {
                    return $word;
                }

                return mb_strtoupper(mb_substr($word, 0, 1)).mb_substr($word, 1);
            })
            ->implode(' ');
    }

    private static function normalizeKey(string $value): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $value) ?? $value));
    }
}
