<?php

namespace App\Ark\Operations\Settings;

use Illuminate\Support\Facades\Schema;

/**
 * Shop settings columns are checked several times while a repair order opens.
 * Listing them once keeps those checks off the database.
 */
final class ShopSettingsSchema
{
    /** @var array<string, true>|null */
    private static ?array $columns = null;

    public static function has(string $column): bool
    {
        if (self::$columns === null) {
            self::$columns = [];

            foreach (Schema::getColumnListing('shop_settings') as $name) {
                self::$columns[strtolower($name)] = true;
            }
        }

        return isset(self::$columns[strtolower($column)]);
    }

    public static function flush(): void
    {
        self::$columns = null;
    }
}
