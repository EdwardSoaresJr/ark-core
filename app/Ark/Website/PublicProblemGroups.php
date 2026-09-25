<?php

namespace App\Ark\Website;

/**
 * Splits the published catalog for the problem hub.
 *
 * URLs stay under /common-problems/. Service pages and local landing
 * pages stay published, and they are not listed here.
 */
final class PublicProblemGroups
{
    /**
     * @var list<string>
     */
    public const SYMPTOM_SLUGS = [
        'check-engine-light',
        'car-wont-start',
        'engine-overheating',
        'brake-noise',
        'wheel-bearing-noise',
        'suspension-noise',
        'ac-not-cold',
        'misfire-under-load',
        'battery-keeps-dying',
        'oil-leak',
        'transmission-slipping',
        'abs-light',
        'rough-idle',
        'subaru-overheating',
        'jeep-overheating',
        'jeep-death-wobble',
    ];

    /**
     * @var list<string>
     */
    public const CODE_SLUGS = [
        'p0300',
        'p0301',
        'p0302',
        'p0303',
        'p0304',
        'p0171',
        'p0174',
        'p0420',
        'p0430',
        'p0442',
        'p0455',
        'p0128',
        'p0101',
        'p0401',
        'p0340',
        'p0135',
        'p0016',
    ];

    /**
     * @var list<string>
     */
    public const SERVICE_SLUGS = [
        'electrical-diagnostics',
        'car-fluid-service',
        'honda-timing-belt',
        'burnt-transmission-fluid',
        'transmission-fluid-change',
        'brake-fluid-service',
    ];

    /**
     * @var list<string>
     */
    public const UNPROMOTED_SLUGS = [
        'auto-repair-colorado-springs',
        'mechanic-colorado-springs',
        'car-diagnostics-colorado-springs',
        'brake-repair-colorado-springs',
        'tune-up-colorado-springs',
        'audi-repair-colorado-springs',
    ];

    /**
     * @param  list<array<string, mixed>>  $problems
     * @return array{symptoms: list<array<string, mixed>>, codes: list<array<string, mixed>>}
     */
    public static function split(array $problems): array
    {
        $bySlug = [];
        foreach ($problems as $problem) {
            $slug = trim((string) ($problem['slug'] ?? ''));
            if ($slug === '') {
                continue;
            }
            $bySlug[$slug] = $problem;
        }

        $symptoms = self::take($bySlug, self::SYMPTOM_SLUGS);
        $codes = self::take($bySlug, self::CODE_SLUGS);

        foreach (array_merge(self::SERVICE_SLUGS, self::UNPROMOTED_SLUGS) as $slug) {
            unset($bySlug[$slug]);
        }

        foreach ($bySlug as $problem) {
            $symptoms[] = $problem;
        }

        return [
            'symptoms' => $symptoms,
            'codes' => $codes,
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $bySlug
     * @param  list<string>  $slugs
     * @return list<array<string, mixed>>
     */
    private static function take(array &$bySlug, array $slugs): array
    {
        $taken = [];
        foreach ($slugs as $slug) {
            if (! isset($bySlug[$slug])) {
                continue;
            }
            $taken[] = $bySlug[$slug];
            unset($bySlug[$slug]);
        }

        return $taken;
    }
}
