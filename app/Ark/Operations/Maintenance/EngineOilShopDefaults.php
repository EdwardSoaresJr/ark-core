<?php

namespace App\Ark\Operations\Maintenance;

use App\Ark\Operations\Settings\ShopSettings;

/**
 * Shop preparation defaults for Engine Oil — never vehicle specification.
 */
final class EngineOilShopDefaults
{
    public const DEFAULT_PACKAGE_PRICE_CENTS = 8995;

    public const DEFAULT_INCLUDED_QUART_ALLOWANCE = '5.00';

    /**
     * @return array{
     *     preferred_oil_brand: ?string,
     *     include_washer_by_default: bool,
     *     package_price_cents: int,
     *     included_quart_allowance: string
     * }
     */
    public static function fromShopSettings(?ShopSettings $settings = null): array
    {
        $settings ??= ShopSettings::current();
        $raw = is_array($settings->maintenance_engine_oil) ? $settings->maintenance_engine_oil : [];

        $brand = trim((string) ($raw['preferred_oil_brand'] ?? ''));
        $packagePrice = (int) ($raw['package_price_cents'] ?? self::DEFAULT_PACKAGE_PRICE_CENTS);
        $allowance = trim((string) ($raw['included_quart_allowance'] ?? self::DEFAULT_INCLUDED_QUART_ALLOWANCE));

        return [
            'preferred_oil_brand' => $brand !== '' ? $brand : null,
            'include_washer_by_default' => (bool) ($raw['include_washer_by_default'] ?? true),
            'package_price_cents' => max(0, $packagePrice),
            'included_quart_allowance' => $allowance !== '' ? $allowance : self::DEFAULT_INCLUDED_QUART_ALLOWANCE,
        ];
    }
}
