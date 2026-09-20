<?php

namespace App\Ark\Operations\Parts;

use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Support\Facades\Schema;

final class PartsCatalogButtonColor
{
    public const YELLOW = 'yellow';

    public const GREEN = 'green';

    public const ORANGE = 'orange';

    public const RED = 'red';

    public const BLUE = 'blue';

    public const NAVY = 'navy';

    public const SLATE = 'slate';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [
            self::YELLOW,
            self::GREEN,
            self::ORANGE,
            self::RED,
            self::BLUE,
            self::NAVY,
            self::SLATE,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::YELLOW => 'Yellow',
            self::GREEN => 'Green',
            self::ORANGE => 'Orange',
            self::RED => 'Red',
            self::BLUE => 'Blue',
            self::NAVY => 'Navy',
            self::SLATE => 'Slate',
        ];
    }

    public static function normalize(?string $value): string
    {
        $color = strtolower(trim((string) $value));

        return in_array($color, self::values(), true) ? $color : self::SLATE;
    }

    public static function defaultFor(string $key, string $label = ''): string
    {
        $haystack = strtolower(preg_replace('/[^a-z0-9]+/i', '', $key.' '.$label) ?? '');

        if ($key === PartsCatalogProvider::PartsTech->value || str_contains($haystack, 'partstech')) {
            return self::YELLOW;
        }

        if ($key === PartsCatalogProvider::Nexpart->value || str_contains($haystack, 'nexpart')) {
            return self::NAVY;
        }

        if ($key === PartsCatalogProvider::RepairLink->value || str_contains($haystack, 'repairlink')) {
            return self::BLUE;
        }

        if (str_contains($haystack, 'oreilly')) {
            return self::GREEN;
        }

        if (str_contains($haystack, 'autozone')) {
            return self::ORANGE;
        }

        if (str_contains($haystack, 'firstcall')) {
            return self::RED;
        }

        return self::SLATE;
    }

    public static function className(string $color): string
    {
        return 'ops-review-action--catalog-'.self::normalize($color);
    }

    /**
     * @return array<string, string>
     */
    public static function shopMap(): array
    {
        if (! Schema::hasColumn('shop_settings', 'parts_catalog_button_colors')) {
            return [];
        }

        $raw = ShopSettings::current()->parts_catalog_button_colors;

        if (! is_array($raw)) {
            return [];
        }

        $colors = [];

        foreach ($raw as $key => $color) {
            if (! is_string($key) || $key === '') {
                continue;
            }

            $colors[$key] = self::normalize(is_string($color) ? $color : null);
        }

        return $colors;
    }

    public static function persistShopColor(string $key, string $color): void
    {
        if (! Schema::hasColumn('shop_settings', 'parts_catalog_button_colors')) {
            return;
        }

        $colors = self::shopMap();
        $colors[$key] = self::normalize($color);

        ShopSettings::current()->persistTrusted([
            'parts_catalog_button_colors' => $colors,
        ]);
    }

    public static function resolve(?string $stored, string $key, string $label = ''): string
    {
        if (filled($stored)) {
            return self::normalize($stored);
        }

        $shop = self::shopMap()[$key] ?? null;

        if (filled($shop)) {
            return self::normalize($shop);
        }

        return self::defaultFor($key, $label);
    }
}
