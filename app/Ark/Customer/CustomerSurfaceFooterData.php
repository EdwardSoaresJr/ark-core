<?php

namespace App\Ark\Customer;

use App\Ark\Operations\Leads\Public\PublicSurfaceSettings;
use App\Ark\Operations\Leads\Public\ShopSocialProfiles;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Support\Facades\Route;

final class CustomerSurfaceFooterData
{
    /**
     * @return array<string, mixed>
     */
    public static function viewData(): array
    {
        $shop = ShopSettings::current();
        $shopName = $shop->displayName();
        $publicSurface = PublicSurfaceSettings::current();

        $streetAddress = $shop->publicationStreetAddress();
        $cityState = trim(implode(', ', array_filter([$shop->city, $shop->state]))) ?: 'Colorado Springs, CO';
        $postalCode = trim((string) ($shop->postal_code ?? '')) ?: '80909';

        $addressParts = array_filter([
            $streetAddress,
            $cityState,
            $postalCode,
        ]);
        $addressLine = $addressParts !== [] ? implode(' · ', $addressParts) : 'Colorado Springs, CO';

        $phoneDisplay = PhoneNumber::display($shop->phone) ?: '(719) 413-6227';
        $phoneTel = preg_replace('/\D+/', '', (string) $shop->phone) ?: '7194136227';

        $mapsQuery = urlencode(trim(implode(' ', array_filter([
            $shopName,
            $streetAddress,
            $shop->city,
            $shop->state,
            $shop->postal_code,
        ]))));

        $googleReviewsUrl = filled($publicSurface['google_reviews_url'])
            ? (string) $publicSurface['google_reviews_url']
            : 'https://www.google.com/maps/search/?api=1&query='.$mapsQuery;

        $googleMapsUrl = filled($shop->address_line_1)
            ? 'https://www.google.com/maps/search/?api=1&query='.$mapsQuery
            : $googleReviewsUrl;

        $commonProblemsUrl = CustomerSurfaceUrls::commonProblems();
        $financingUrl = Route::has('public.financing') ? route('public.financing') : null;
        $warrantyUrl = Route::has('public.warranty') ? route('public.warranty') : null;
        $repairPalUrl = Route::has('public.repairpal.certified') ? route('public.repairpal.certified') : null;
        $portalUrl = Route::has('portal.access') ? CustomerSurfaceUrls::portalAccess() : null;
        $privacyUrl = Route::has('public.privacy') ? route('public.privacy') : null;
        $termsUrl = Route::has('public.terms') ? route('public.terms') : null;

        $diagnosticsUrl = Route::has('public.common-problems.show')
            ? route('public.common-problems.show', 'car-diagnostics-colorado-springs')
            : $commonProblemsUrl;

        $googleRating = (float) ($publicSurface['google_rating'] ?? 0);
        $googleReviewCount = max(0, (int) ($publicSurface['google_review_count'] ?? 0));

        $base = [
            'shop_name' => $shopName,
            'address_line' => $addressLine,
            'street_address' => $streetAddress !== '' ? $streetAddress : $addressLine,
            'city_state' => $cityState !== '' ? $cityState : 'Colorado Springs, CO',
            'phone_display' => $phoneDisplay,
            'phone_tel' => $phoneTel,
            'business_hours_label' => $publicSurface['business_hours_label'],
            'google_maps_url' => $googleMapsUrl,
            'google_reviews_url' => $googleReviewsUrl,
            'google_rating' => $googleRating > 0 ? number_format($googleRating, 1) : null,
            'google_review_count' => $googleReviewCount,
            'common_problems_url' => $commonProblemsUrl,
            'diagnostics_url' => $diagnosticsUrl,
            'financing_url' => $financingUrl,
            'warranty_url' => $warrantyUrl,
            'repairpal_url' => $repairPalUrl,
            'portal_url' => $portalUrl,
            'privacy_url' => $privacyUrl,
            'terms_url' => $termsUrl,
            'variant' => self::usesTrustFooter() ? 'trust' : 'compact',
        ];

        if ($base['variant'] !== 'trust') {
            return $base;
        }

        /** @var list<array{label: string, href: string}> $navLinks */
        $navLinks = array_values(array_filter([
            Route::has('public.book') ? ['label' => 'Book Appointment', 'href' => route('public.book')] : null,
            ['label' => 'Diagnostics', 'href' => $diagnosticsUrl],
            ['label' => 'Common Problems', 'href' => $commonProblemsUrl],
            filled($financingUrl) ? ['label' => 'Financing', 'href' => $financingUrl] : null,
            filled($warrantyUrl) ? ['label' => 'Warranty', 'href' => $warrantyUrl] : null,
            filled($repairPalUrl) ? ['label' => 'RepairPal', 'href' => $repairPalUrl] : null,
            filled($portalUrl) ? [
                'label' => auth('portal')->check() ? 'My Account' : 'Sign In',
                'href' => auth('portal')->check() ? CustomerSurfaceUrls::portalHome() : $portalUrl,
            ] : null,
        ]));

        return array_merge($base, [
            'service_tagline' => 'Diagnostics first. Then the repair.',
            'nav_links' => $navLinks,
            'trust_points' => [],
            'show_google_rating' => false,
            'social_links' => ShopSocialProfiles::forDisplay($publicSurface),
            'social_compact_links' => array_values(array_filter(
                ShopSocialProfiles::forDisplay($publicSurface),
                fn (array $link): bool => in_array($link['key'], ['facebook', 'instagram', 'nextdoor', 'google_reviews'], true),
            )),
        ]);
    }

    private static function usesTrustFooter(): bool
    {
        // Customer shell only — public site, portal/account, and staff portal previews
        // share the same trust footer (not the compact legacy strip).
        return true;
    }
}
