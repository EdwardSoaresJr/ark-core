<?php

namespace App\Ark\Platform\Website;

use App\Ark\Operations\Settings\ShopSettings;

final class CoreWebsiteDocument
{
    /**
     * Shop identity Core may publish. Marketing CMS copy is not stored in public Core.
     *
     * @return array{document: array<string, mixed>, hash: string}
     */
    public static function read(): array
    {
        ShopSettings::forgetCurrent();
        $shop = ShopSettings::current();

        $document = [
            'shop_name' => (string) ($shop->shop_name ?? ''),
            'phone' => (string) ($shop->phone ?? ''),
            'email' => (string) ($shop->email ?? ''),
            'website' => (string) ($shop->website ?? ''),
            'google_reviews_url' => (string) ($shop->google_reviews_url ?? ''),
            'address_line_1' => (string) ($shop->address_line_1 ?? ''),
            'address_line_2' => (string) ($shop->address_line_2 ?? ''),
            'city' => (string) ($shop->city ?? ''),
            'state' => (string) ($shop->state ?? ''),
            'postal_code' => (string) ($shop->postal_code ?? ''),
            'headline' => (string) ($shop->shop_name ?? ''),
            'positioning_lede' => (string) ($shop->website ?? ''),
        ];

        return [
            'document' => $document,
            'hash' => WebsiteDocumentHash::hash($document),
        ];
    }
}
