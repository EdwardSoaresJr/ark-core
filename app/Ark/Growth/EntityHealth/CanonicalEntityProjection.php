<?php

namespace App\Ark\Growth\EntityHealth;

use App\Ark\Growth\PublicSurface\PublicMarketingUrl;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\Settings\ShopSettings;

final class CanonicalEntityProjection
{
    /**
     * @return list<array{key: string, label: string, value: string, complete: bool}>
     */
    public static function fields(): array
    {
        $shop = ShopSettings::current();

        $street = $shop->googleMatchedStreetAddress();
        $city = trim((string) $shop->city);
        $region = trim((string) $shop->state);
        $postal = trim((string) $shop->postal_code);

        $cityLine = implode(', ', array_filter([$city, trim($region.' '.$postal)]));
        $addressDisplay = implode("\n", array_filter([$street !== '' ? $street : null, $cityLine !== '' ? $cityLine : null])) ?: '—';

        $phone = PhoneNumber::display($shop->phone) ?? '—';
        $website = trim((string) $shop->website);
        $publicWebsite = PublicMarketingUrl::baseUrl();

        return [
            [
                'key' => 'business_name',
                'label' => 'Business name',
                'value' => $shop->displayName(),
                'complete' => trim((string) $shop->shop_name) !== '',
            ],
            [
                'key' => 'address',
                'label' => 'Address',
                'value' => $addressDisplay,
                'complete' => $street !== '' && $city !== '' && $region !== '' && $postal !== '',
            ],
            [
                'key' => 'phone',
                'label' => 'Phone',
                'value' => $phone,
                'complete' => PhoneNumber::normalize($shop->phone) !== null,
            ],
            [
                'key' => 'website',
                'label' => 'Website',
                'value' => $website !== '' ? $website : $publicWebsite,
                'complete' => $website !== '' || $publicWebsite !== '',
            ],
            [
                'key' => 'primary_category',
                'label' => 'Primary category',
                'value' => (string) config('entity_health.primary_category', 'Auto Repair Shop'),
                'complete' => true,
            ],
        ];
    }
}
