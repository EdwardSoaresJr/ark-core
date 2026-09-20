<?php

namespace App\Ark\Install;

final class DeferredMigrationLoader
{
    /**
     * @return list<string>
     */
    public static function paths(): array
    {
        if ((bool) config('ark.preserve_website_growth_schema', false)) {
            return [];
        }

        return [database_path('migrations/deferred')];
    }
}
