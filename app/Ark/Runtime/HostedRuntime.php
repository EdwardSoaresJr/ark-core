<?php

namespace App\Ark\Runtime;

use App\Ark\Install\InstallationState;

/**
 * Hosted shops boot before a tenant database is selected.
 * The image default is a local database that is not running.
 */
final class HostedRuntime
{
    public static function catalogConfigured(): bool
    {
        $catalog = getenv('ARK_HOSTED_TENANTS');

        return is_string($catalog) && $catalog !== '' && is_file($catalog);
    }

    public static function shouldUseFileDriversBeforeInstall(): bool
    {
        return InstallationState::isNotInstalled() && ! self::catalogConfigured();
    }

    public static function tenantDatabaseSelected(): bool
    {
        if (! self::catalogConfigured()) {
            return true;
        }

        $connection = (string) config('database.default');
        $host = (string) config("database.connections.{$connection}.host");
        $database = (string) config("database.connections.{$connection}.database");

        return ! self::isUnconfiguredLocalDatabase($host, $database);
    }

    public static function isUnconfiguredLocalDatabase(string $host, string $database): bool
    {
        return in_array($host, ['127.0.0.1', 'localhost', ''], true)
            && $database === 'laravel';
    }
}
