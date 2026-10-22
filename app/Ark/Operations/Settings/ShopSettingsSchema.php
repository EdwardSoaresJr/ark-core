<?php

namespace App\Ark\Operations\Settings;

use App\Ark\Runtime\DatabaseReadScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Shop settings columns are checked several times while a repair order opens.
 * The listing is remembered for the process, once per database, and replaced
 * when that database's migration history changes.
 * A request or queue job does not discard it. Call flush() after a schema
 * change in this process; a migration that commits in any process is seen on
 * the next unit of work because the migrations row id is read again.
 */
final class ShopSettingsSchema
{
    /** @var array<string, array<string, true>> */
    private static array $columns = [];

    /** @var array<string, string> */
    private static array $versions = [];

    /** @var array<string, true> */
    private static array $confirmed = [];

    public static function has(string $column): bool
    {
        $scope = DatabaseReadScope::key();

        if (! isset(self::$confirmed[$scope])) {
            self::confirm($scope);
        }

        return isset(self::$columns[$scope][strtolower($column)]);
    }

    /**
     * The next read rechecks migration history and reuses the column list when
     * it has not changed. Repair-order request memory is a separate cache.
     */
    public static function revalidate(): void
    {
        self::$confirmed = [];
    }

    /**
     * Drops remembered columns. Migrations in this process call this. A test
     * or schema change that does not write a migrations row must call it too.
     */
    public static function flush(): void
    {
        self::$columns = [];
        self::$versions = [];
        self::$confirmed = [];
    }

    private static function confirm(string $scope): void
    {
        $version = self::migrationVersion();

        if (isset(self::$columns[$scope], self::$versions[$scope]) && self::$versions[$scope] === $version) {
            self::$confirmed[$scope] = true;

            return;
        }

        $columns = [];

        foreach (Schema::getColumnListing('shop_settings') as $name) {
            $columns[strtolower($name)] = true;
        }

        self::$columns[$scope] = $columns;
        self::$versions[$scope] = $version;
        self::$confirmed[$scope] = true;
    }

    private static function migrationVersion(): string
    {
        if (! Schema::hasTable('migrations')) {
            return '0';
        }

        return (string) (DB::table('migrations')->max('id') ?? '0');
    }
}
