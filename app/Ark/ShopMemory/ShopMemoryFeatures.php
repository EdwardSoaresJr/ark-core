<?php

namespace App\Ark\ShopMemory;

use App\Ark\Operations\Settings\ShopSettings;

/**
 * Shop Memory capability toggles — Settings, not FeatureFlag package.
 */
final class ShopMemoryFeatures
{
    public static function providerEnabled(string $key): bool
    {
        $settings = self::settings();

        return (bool) ($settings['providers'][$key] ?? false);
    }

    public static function addConcernPopupEnabled(): bool
    {
        $settings = self::settings();

        return (bool) ($settings['surfaces']['add_concern_popup'] ?? true);
    }

    public static function aiRewriteEnabled(): bool
    {
        return self::providerEnabled(ShopMemoryProviderCatalog::AI_REWRITE);
    }

    /**
     * @return array{providers: array<string, bool>, surfaces: array<string, bool>}
     */
    public static function settings(): array
    {
        $defaults = ShopMemoryProviderCatalog::defaultSettings();

        try {
            $raw = ShopSettings::current()->shop_memory;
        } catch (\Throwable) {
            return $defaults;
        }

        if (! is_array($raw)) {
            return $defaults;
        }

        return [
            'providers' => array_merge(
                $defaults['providers'],
                is_array($raw['providers'] ?? null) ? $raw['providers'] : [],
            ),
            'surfaces' => array_merge(
                $defaults['surfaces'],
                is_array($raw['surfaces'] ?? null) ? $raw['surfaces'] : [],
            ),
        ];
    }
}
