<?php

namespace App\Ark\Operations\EstimatePricing;

use App\Ark\Operations\Settings\ShopSettings;

/**
 * Suggests catalog tax profiles from address associations.
 * City/ZIP/state matches are suggestive — never conclusive assignment.
 */
final class TaxJurisdictionMatcher
{
    /**
     * @param  array{state?: ?string, county?: ?string, city?: ?string, postal_code?: ?string}  $address
     * @return array{
     *     candidates: list<array{key: string, name: string, score: int, reasons: list<string>}>,
     *     ambiguous: bool,
     *     exact_claim: false,
     *     fallback_key: ?string,
     *     note: string
     * }
     */
    public function suggest(ShopSettings $settings, array $address): array
    {
        $profiles = collect(ShopTaxCatalog::profilesFromShopSettings($settings))
            ->filter(fn (array $profile): bool => (bool) ($profile['is_active'] ?? true))
            ->values();

        $state = $this->normalizeToken($address['state'] ?? null);
        $county = $this->normalizeToken($address['county'] ?? null);
        $city = $this->normalizeToken($address['city'] ?? null);
        $zip = $this->normalizeZip($address['postal_code'] ?? null);

        $scored = [];

        foreach ($profiles as $profile) {
            $reasons = [];
            $score = 0;

            $profileState = $this->normalizeToken($profile['state'] ?? null);
            if ($state !== null && $profileState !== null && $state === $profileState) {
                $score += 10;
                $reasons[] = 'state';
            }

            $counties = collect($profile['counties'] ?? [])
                ->map(fn ($value) => $this->normalizeToken(is_string($value) ? $value : null))
                ->filter()
                ->all();
            if ($county !== null && in_array($county, $counties, true)) {
                $score += 25;
                $reasons[] = 'county';
            }

            $cities = collect($profile['cities'] ?? [])
                ->map(fn ($value) => $this->normalizeToken(is_string($value) ? $value : null))
                ->filter()
                ->all();
            if ($city !== null && in_array($city, $cities, true)) {
                $score += 40;
                $reasons[] = 'city';
            }

            $zips = collect($profile['zip_codes'] ?? [])
                ->map(fn ($value) => $this->normalizeZip(is_string($value) ? $value : null))
                ->filter()
                ->all();
            if ($zip !== null && in_array($zip, $zips, true)) {
                $score += 35;
                $reasons[] = 'zip';
            }

            if ($score === 0) {
                continue;
            }

            $scored[] = [
                'key' => (string) $profile['key'],
                'name' => (string) ($profile['name'] ?? $profile['key']),
                'score' => $score,
                'reasons' => $reasons,
            ];
        }

        usort($scored, function (array $a, array $b): int {
            return [$b['score'], $a['name']] <=> [$a['score'], $b['name']];
        });

        $default = $profiles->first(fn (array $profile): bool => (bool) ($profile['is_default'] ?? false));
        $fallbackKey = is_array($default) ? (string) $default['key'] : ($profiles[0]['key'] ?? null);

        $topScore = $scored[0]['score'] ?? 0;
        $topCount = collect($scored)->where('score', $topScore)->count();
        $ambiguous = $topCount > 1 || ($scored !== [] && $topScore < 50);

        $note = match (true) {
            $scored === [] => 'No associated tax profile matched this address. Select a profile manually or use the shop default.',
            $ambiguous => 'Multiple tax profiles could apply. Confirm the jurisdiction — city/ZIP matches are suggestive only.',
            default => 'Suggested match from shop associations. Confirm before relying on this jurisdiction.',
        };

        return [
            'candidates' => array_values($scored),
            'ambiguous' => $ambiguous || $scored === [],
            'exact_claim' => false,
            'fallback_key' => $fallbackKey !== null ? (string) $fallbackKey : null,
            'note' => $note,
        ];
    }

    private function normalizeToken(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        return mb_strtolower($trimmed);
    }

    private function normalizeZip(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $zip = strtoupper(preg_replace('/\s+/', '', $value) ?? '');

        return $zip !== '' ? $zip : null;
    }
}
