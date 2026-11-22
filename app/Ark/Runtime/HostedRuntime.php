<?php

namespace App\Ark\Runtime;

use App\Ark\Install\InstallationState;

/**
 * Hosted shops boot before a tenant database is selected.
 * The image default is a local database that is not running.
 *
 * Two hosted contracts exist:
 * - Landlord pool images set {@see self::catalogConfigured()} via ARK_HOSTED_TENANTS.
 * - Stancl/SaaS shops select a tenant database through tenancy()->initialize(); they do
 *   not set ARK_HOSTED_TENANTS. {@see self::isHostedProduct()} recognizes both.
 */
final class HostedRuntime
{
    public static function catalogConfigured(): bool
    {
        $catalog = getenv('ARK_HOSTED_TENANTS');

        return is_string($catalog) && $catalog !== '' && is_file($catalog);
    }

    /**
     * True when this process is a hosted shop product (Landlord catalog or Stancl tenant).
     * Standalone Core installs have neither.
     */
    public static function isHostedProduct(): bool
    {
        return self::catalogConfigured() || self::stanclTenantInitialized();
    }

    /**
     * Stancl multi-tenant SaaS: a shop request has an initialized tenant context.
     * Does not invent an ARK_HOSTED_TENANTS catalog file.
     */
    public static function stanclTenantInitialized(): bool
    {
        if (! function_exists('tenancy')) {
            return false;
        }

        try {
            $tenancy = tenancy();
        } catch (\Throwable) {
            return false;
        }

        return (bool) ($tenancy->initialized ?? false)
            && ($tenancy->tenant ?? null) !== null;
    }

    public static function shouldUseFileDriversBeforeInstall(): bool
    {
        return InstallationState::isNotInstalled() && ! self::catalogConfigured();
    }

    public static function tenantDatabaseSelected(): bool
    {
        if (! self::catalogConfigured()) {
            // Standalone Core, or Stancl after initialize (non-catalog hosted): DB is already the shop.
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
