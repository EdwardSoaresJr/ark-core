<?php

namespace App\Ark\Operations\Messaging;

use App\Ark\Operations\Settings\ShopSettings;
use RuntimeException;

/**
 * Customer-facing shop address SMS body — Message Action copy only.
 */
final class ShopAddressSmsCopy
{
    public static function body(?ShopSettings $shop = null): string
    {
        $shop ??= ShopSettings::current();
        $name = trim((string) ($shop->shop_name ?? ''));
        $line1 = trim((string) ($shop->address_line_1 ?? ''));
        $line2 = trim((string) ($shop->address_line_2 ?? ''));
        $city = trim((string) ($shop->city ?? ''));
        $state = trim((string) ($shop->state ?? ''));
        $postal = trim((string) ($shop->postal_code ?? ''));

        if ($line1 === '' && $line2 === '') {
            throw new RuntimeException('Shop address is not configured in Settings.');
        }

        $shopLabel = $name !== '' ? $name : 'Our shop';
        $cityStateZip = trim(implode(' ', array_filter([
            $city !== '' && $state !== '' ? $city.', '.$state : ($city !== '' ? $city : $state),
            $postal,
        ])));

        $mapsQuery = urlencode(trim(implode(' ', array_filter([
            $shopLabel,
            $shop->googleMatchedStreetAddress() ?: $line1,
            $city,
            $state,
            $postal,
        ]))));

        $mapsUrl = 'https://maps.google.com/?q='.$mapsQuery;

        $lines = array_values(array_filter([
            $shopLabel,
            $line1 !== '' ? $line1 : null,
            $line2 !== '' ? $line2 : null,
            $cityStateZip !== '' ? $cityStateZip : null,
            '',
            'Google Maps:',
            $mapsUrl,
        ], fn (mixed $line): bool => $line !== null));

        return implode("\n", $lines);
    }
}
