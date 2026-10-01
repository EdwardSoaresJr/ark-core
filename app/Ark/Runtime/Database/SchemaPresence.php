<?php

namespace App\Ark\Runtime\Database;

use Illuminate\Support\Facades\Schema;

/**
 * Memoized schema-existence guards for hot render paths.
 *
 * Only positive answers are cached: once a table or column exists it never
 * disappears in a running deployment, while a negative answer (mid-deploy,
 * before migrations) must keep re-checking until the migration lands.
 */
final class SchemaPresence
{
    /** @var array<string, true> */
    private static array $present = [];

    public static function hasTable(string $table): bool
    {
        if (isset(self::$present[$table])) {
            return true;
        }

        if (Schema::hasTable($table)) {
            self::$present[$table] = true;

            return true;
        }

        return false;
    }

    public static function hasColumn(string $table, string $column): bool
    {
        $key = $table.'.'.$column;

        if (isset(self::$present[$key])) {
            return true;
        }

        if (Schema::hasColumn($table, $column)) {
            self::$present[$key] = true;

            return true;
        }

        return false;
    }

    /**
     * Test-only escape hatch for schema-mutation scenarios.
     */
    public static function flush(): void
    {
        self::$present = [];
    }
}
