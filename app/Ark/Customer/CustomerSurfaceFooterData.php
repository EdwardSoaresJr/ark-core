<?php

namespace App\Ark\Customer;

use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Telephony\TelephonyBusinessHoursLabel;
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

        $streetAddress = $shop->publicationStreetAddress();
        $cityState = trim(implode(', ', array_filter([$shop->city, $shop->state]))) ?: null;
        $postalCode = trim((string) ($shop->postal_code ?? '')) ?: null;

        $addressParts = array_filter([
            $streetAddress,
            $cityState,
            $postalCode,
        ]);
        $addressLine = $addressParts !== [] ? implode(' · ', $addressParts) : ($cityState ?? $shopName);

        $phoneDisplay = PhoneNumber::display($shop->phone) ?: null;
        $phoneTel = preg_replace('/\D+/', '', (string) $shop->phone) ?: null;

        $mapsQuery = urlencode(trim(implode(' ', array_filter([
            $shopName,
            $streetAddress,
            $shop->city,
            $shop->state,
            $shop->postal_code,
        ]))));

        $googleMapsUrl = filled($shop->address_line_1)
            ? 'https://www.google.com/maps/search/?api=1&query='.$mapsQuery
            : null;

        $portalUrl = Route::has('portal.access') ? CustomerSurfaceUrls::portalAccess() : null;

        /** @var list<array{label: string, href: string}> $navLinks */
        $navLinks = array_values(array_filter([
            filled($portalUrl) ? [
                'label' => auth('portal')->check() ? 'My Account' : 'Sign In',
                'href' => auth('portal')->check() ? CustomerSurfaceUrls::portalHome() : $portalUrl,
            ] : null,
        ]));

        return [
            'shop_name' => $shopName,
            'address_line' => $addressLine,
            'street_address' => $streetAddress !== '' ? $streetAddress : $addressLine,
            'city_state' => $cityState,
            'phone_display' => $phoneDisplay,
            'phone_tel' => $phoneTel,
            'business_hours_label' => TelephonyBusinessHoursLabel::fromCallFlow(),
            'google_maps_url' => $googleMapsUrl,
            'portal_url' => $portalUrl,
            'nav_links' => $navLinks,
        ];
    }
}
