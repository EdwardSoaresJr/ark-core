<?php

namespace App\Ark\Operations\Leads\Public;

/**
 * Picks a featured Google review for a public page — theme match first, then stable rotation.
 * Home social proof uses {@see rotating()} so each refresh can show a different quote.
 */
final class PublicFeaturedReviewProjection
{
    /**
     * Random featured review for Home social proof — new pick each request / refresh.
     *
     * @param  list<array{quote: string, attribution: string}>  $reviews
     * @return array{quote: string, attribution: string}|null
     */
    public static function rotating(array $reviews): ?array
    {
        $normalized = self::normalizedReviews($reviews);

        if ($normalized === []) {
            return null;
        }

        return $normalized[random_int(0, count($normalized) - 1)];
    }

    /**
     * @param  list<array{quote: string, attribution: string}>  $reviews
     * @return array{quote: string, attribution: string}|null
     */
    public static function forPage(?string $pageKey, array $reviews): ?array
    {
        $reviews = self::normalizedReviews($reviews);

        if ($reviews === []) {
            return null;
        }

        $keywords = self::keywordsForPage($pageKey);

        if ($keywords !== []) {
            foreach ($reviews as $review) {
                $haystack = mb_strtolower($review['quote']);

                foreach ($keywords as $keyword) {
                    if (str_contains($haystack, $keyword)) {
                        return $review;
                    }
                }
            }
        }

        if ($pageKey === null || $pageKey === '') {
            return $reviews[0];
        }

        $index = abs(crc32($pageKey)) % count($reviews);

        return $reviews[$index];
    }

    /**
     * @param  list<array{quote: string, attribution: string}>  $reviews
     * @return list<array{quote: string, attribution: string}>
     */
    private static function normalizedReviews(array $reviews): array
    {
        $normalized = [];

        foreach ($reviews as $review) {
            if (! is_array($review)) {
                continue;
            }

            $quote = trim((string) ($review['quote'] ?? ''));

            if ($quote === '') {
                continue;
            }

            $normalized[] = [
                'quote' => $quote,
                'attribution' => trim((string) ($review['attribution'] ?? '')),
            ];
        }

        return $normalized;
    }

    /**
     * @return list<string>
     */
    private static function keywordsForPage(?string $pageKey): array
    {
        if ($pageKey === null || $pageKey === '') {
            return [];
        }

        $slug = mb_strtolower($pageKey);

        return match (true) {
            $slug === 'homepage' || $slug === 'home' => [
                'another shop',
                'underfilled',
                'missed',
                'found it only',
                'diagnostic',
                'methodical',
                'unnecessary',
            ],
            str_contains($slug, 'overheat') || str_contains($slug, 'cooling') => ['overheat', 'coolant', 'radiator', 'thermostat'],
            str_contains($slug, 'brake') => ['brake', 'rotor', 'pad'],
            str_contains($slug, 'battery') || str_contains($slug, 'wont-start') || str_contains($slug, 'no-start') => ['battery', 'start', 'crank', 'charging'],
            str_contains($slug, 'electrical') || str_contains($slug, 'abs') => ['electrical', 'electric', 'wiring', 'abs'],
            str_contains($slug, 'check-engine') || str_contains($slug, 'p0171') || str_contains($slug, 'idle') => ['engine', 'diagnostic', 'code', 'misfire'],
            str_contains($slug, 'transmission') => ['transmission', 'trans'],
            str_contains($slug, 'ac-not') || str_contains($slug, 'ac-') => ['ac', 'air conditioning', 'cold'],
            str_contains($slug, 'wheel-bearing') || str_contains($slug, 'death-wobble') || str_contains($slug, 'jeep') => ['jeep', 'bearing', 'wobble', 'suspension'],
            str_contains($slug, 'subaru') => ['subaru'],
            str_contains($slug, 'audi') => ['audi'],
            str_contains($slug, 'honda') || str_contains($slug, 'timing') => ['honda', 'timing'],
            str_contains($slug, 'oil-leak') || str_contains($slug, 'fluid') => ['oil', 'leak', 'fluid'],
            str_contains($slug, 'diagnostic') || str_contains($slug, 'mechanic') => ['diagnostic', 'honest', 'explain', 'trust'],
            default => [],
        };
    }
}
