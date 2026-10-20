<?php

namespace App\Ark\Operations\Settings;

use App\Ark\Runtime\DatabaseReadScope;
use Illuminate\Support\Facades\Schema;

/**
 * Shop settings columns are checked several times while a repair order opens.
 * Listing them once keeps those checks off the database.
 * The listing is kept per database so a worker that changes shops cannot reuse another shop's columns.
 */
final class ShopSettingsSchema
{
    /** @var array<string, array<string, true>> */
    private static array $columns = [];

    public static function has(string $column): bool
    {
        $scope = DatabaseReadScope::key();

        if (! isset(self::$columns[$scope])) {
            self::$columns[$scope] = [];

            foreach (Schema::getColumnListing('shop_settings') as $name) {
                self::$columns[$scope][strtolower($name)] = true;
            }
        }

        return isset(self::$columns[$scope][strtolower($column)]);
    }

    public static function flush(): void
    {
        self::$columns = [];
    }
}
