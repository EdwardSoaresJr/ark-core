<?php

namespace App\Ark\Growth\Seo;

use App\Ark\Growth\PublicSurface\PublicMarketingUrl;
use App\Ark\Operations\Leads\Public\PublicSurfaceSettings;
use App\Ark\Operations\Leads\Public\ShopSocialProfiles;
use App\Ark\Operations\Settings\ShopSettings;
use App\Support\Mail\ShopMailBranding;

final class ShopSeoContext
{
    /**
     * @return array<string, mixed>
     */
    public static function resolve(?string $pageDescription = null): array
    {
        $shop = ShopSettings::current();
        $publicSurface = PublicSurfaceSettings::current();
        $shopName = self::shopDisplayName($shop);
        $image = PublicMarketingUrl::absoluteIfRelative(ShopMailBranding::logoUrl());
        $photos = PublicSurfaceSettings::photosForDisplay();

        if ($image === null && $photos !== []) {
            $image = PublicMarketingUrl::absoluteIfRelative($photos[0]['url'] ?? null);
        }

        $telephone = self::e164Phone((string) $shop->phone) ?? self::e164Phone('7194136227');
        $sameAs = ShopSocialProfiles::sameAsUrls($publicSurface);

        $context = [
            'name' => $shopName,
            'description' => $pageDescription ?? (string) config('public_seo.home.description', ''),
            'telephone' => $telephone,
            'image' => $image,
            'logo' => $image,
            'address' => self::postalAddress($shop),
        ];

        if ($sameAs !== []) {
            $context['sameAs'] = $sameAs;
        }

        // Do not emit AggregateRating / Review on AutoRepair, LocalBusiness, or Organization.
        // Google treats self-hosted ratings about the business as self-serving and ineligible
        // for review snippets. Visible Google rating chips remain customer-facing trust proof only.

        return $context;
    }

    /**
     * @return array<string, string>|null
     */
    private static function postalAddress(ShopSettings $shop): ?array
    {
        $street = $shop->publicationStreetAddress();
        $city = trim((string) $shop->city) !== '' ? trim((string) $shop->city) : 'Colorado Springs';
        $region = trim((string) $shop->state) !== '' ? trim((string) $shop->state) : 'CO';
        $postal = trim((string) $shop->postal_code) !== '' ? trim((string) $shop->postal_code) : '80909';

        return [
            '@type' => 'PostalAddress',
            'streetAddress' => $street,
            'addressLocality' => $city,
            'addressRegion' => $region,
            'postalCode' => $postal,
            'addressCountry' => 'US',
        ];
    }

    private static function shopDisplayName(ShopSettings $shop): string
    {
        return $shop->displayName();
    }

    private static function e164Phone(string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (strlen($digits) === 10) {
            return '+1'.$digits;
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
            return '+'.$digits;
        }

        return $digits !== '' ? '+'.$digits : null;
    }
}
