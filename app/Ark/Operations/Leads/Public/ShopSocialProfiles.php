<?php

namespace App\Ark\Operations\Leads\Public;

/**
 * Public social / review profiles — presentation from PublicSurfaceSettings.
 * Never hardcode profile URLs in Blade; empty URLs are omitted from display and sameAs.
 */
final class ShopSocialProfiles
{
    /**
     * @var array<string, array{key: string, label: string, description: string, brand: string}>
     */
    private const CATALOG = [
        'facebook' => [
            'key' => 'facebook',
            'label' => 'Facebook',
            'description' => 'Shop updates, photos, and community events',
            'brand' => 'facebook',
        ],
        'instagram' => [
            'key' => 'instagram',
            'label' => 'Instagram',
            'description' => 'Behind the scenes and repair highlights',
            'brand' => 'instagram',
        ],
        'nextdoor' => [
            'key' => 'nextdoor',
            'label' => 'Nextdoor',
            'description' => 'Local neighborhood updates and recommendations',
            'brand' => 'nextdoor',
        ],
        'google_reviews' => [
            'key' => 'google_reviews',
            'label' => 'Google Reviews',
            'description' => 'See what local customers are saying',
            'brand' => 'google',
        ],
        'youtube' => [
            'key' => 'youtube',
            'label' => 'YouTube',
            'description' => 'Repair explainers and shop videos',
            'brand' => 'youtube',
        ],
        'arkademy' => [
            'key' => 'arkademy',
            'label' => 'ARKademy',
            'description' => 'Repair knowledge from the shop floor',
            'brand' => 'arkademy',
        ],
    ];

    /**
     * @return array{
     *     facebook_url: string|null,
     *     instagram_url: string|null,
     *     nextdoor_url: string|null,
     *     youtube_url: string|null,
     *     arkademy_url: string|null
     * }
     */
    public static function urlsFromSettings(?array $publicSurface = null): array
    {
        $publicSurface ??= PublicSurfaceSettings::current();
        $profiles = is_array($publicSurface['social_profiles'] ?? null)
            ? $publicSurface['social_profiles']
            : [];

        return [
            'facebook_url' => self::nonEmptyUrl($profiles['facebook_url'] ?? null),
            'instagram_url' => self::nonEmptyUrl($profiles['instagram_url'] ?? null),
            'nextdoor_url' => self::nonEmptyUrl($profiles['nextdoor_url'] ?? null),
            'youtube_url' => self::nonEmptyUrl($profiles['youtube_url'] ?? null),
            'arkademy_url' => self::nonEmptyUrl($profiles['arkademy_url'] ?? null),
        ];
    }

    /**
     * Connect With Us rows for public surfaces (only channels with a URL).
     *
     * @return list<array{key: string, label: string, description: string, href: string, brand: string}>
     */
    public static function forDisplay(?array $publicSurface = null): array
    {
        $publicSurface ??= PublicSurfaceSettings::current();
        $urls = self::urlsFromSettings($publicSurface);
        $googleReviewsUrl = self::nonEmptyUrl($publicSurface['google_reviews_url'] ?? null);

        $hrefByKey = [
            'facebook' => $urls['facebook_url'],
            'instagram' => $urls['instagram_url'],
            'nextdoor' => $urls['nextdoor_url'],
            'google_reviews' => $googleReviewsUrl,
            'youtube' => $urls['youtube_url'],
            'arkademy' => $urls['arkademy_url'],
        ];

        $links = [];

        foreach (self::CATALOG as $key => $meta) {
            $href = $hrefByKey[$key] ?? null;

            if ($href === null) {
                continue;
            }

            $links[] = [
                'key' => $meta['key'],
                'label' => $meta['label'],
                'description' => $meta['description'],
                'href' => $href,
                'brand' => $meta['brand'],
            ];
        }

        return $links;
    }

    /**
     * Official profile URLs for Organization / AutoRepair sameAs (exclude Google Maps search).
     *
     * @return list<string>
     */
    public static function sameAsUrls(?array $publicSurface = null): array
    {
        $urls = self::urlsFromSettings($publicSurface);

        return array_values(array_filter([
            $urls['facebook_url'],
            $urls['instagram_url'],
            $urls['nextdoor_url'],
            $urls['youtube_url'],
            $urls['arkademy_url'],
        ]));
    }

    /**
     * @param  array<string, mixed>  $stored
     * @return array{
     *     facebook_url: string|null,
     *     instagram_url: string|null,
     *     nextdoor_url: string|null,
     *     youtube_url: string|null,
     *     arkademy_url: string|null
     * }
     */
    public static function normalize(array $stored): array
    {
        $profiles = is_array($stored['social_profiles'] ?? null)
            ? $stored['social_profiles']
            : [];

        $defaults = PublicSurfaceSettings::DEFAULTS['social_profiles'];

        return [
            'facebook_url' => self::nonEmptyUrl($profiles['facebook_url'] ?? $defaults['facebook_url'] ?? null),
            'instagram_url' => self::nonEmptyUrl($profiles['instagram_url'] ?? $defaults['instagram_url'] ?? null),
            'nextdoor_url' => self::nonEmptyUrl($profiles['nextdoor_url'] ?? $defaults['nextdoor_url'] ?? null),
            'youtube_url' => self::nonEmptyUrl($profiles['youtube_url'] ?? $defaults['youtube_url'] ?? null),
            'arkademy_url' => self::nonEmptyUrl($profiles['arkademy_url'] ?? $defaults['arkademy_url'] ?? null),
        ];
    }

    private static function nonEmptyUrl(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $url = trim($value);

        if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        return $url;
    }
}
