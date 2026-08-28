<?php

namespace App\Ark\Runtime\Surfaces;

use Closure;
use Illuminate\Support\Facades\Route;

final class SurfaceRouting
{
    public static function enabled(): bool
    {
        return (bool) config('surfaces.enabled')
            && filled(config('surfaces.app'))
            && filled(config('surfaces.portal'));
    }

    public static function appHost(): string
    {
        return (string) config('surfaces.app');
    }

    public static function portalHost(): string
    {
        return (string) config('surfaces.portal');
    }

    public static function learnHost(): ?string
    {
        $host = config('surfaces.learn');

        return filled($host) ? (string) $host : null;
    }

    public static function publicHost(): ?string
    {
        $host = config('surfaces.public');

        return filled($host) ? (string) $host : null;
    }

    /** Company product host — ARK Cloud (marketing + trial + cloud dashboard). */
    public static function companyHost(): ?string
    {
        $host = config('surfaces.company');

        return filled($host) ? (string) $host : null;
    }

    public static function companyWwwHost(): ?string
    {
        $host = config('surfaces.company_www');

        return filled($host) ? (string) $host : null;
    }

    /**
     * Future: app.autorepairkeeper.com — Auth + Cloud dashboard.
     * Phase 1 may leave this empty (dashboard still on company host).
     */
    public static function cloudAppHost(): ?string
    {
        $host = config('surfaces.cloud_app');

        return filled($host) ? (string) $host : null;
    }

    public static function companyEnabled(): bool
    {
        return filled(self::companyHost());
    }

    public static function publicEnabled(): bool
    {
        return self::enabled() && filled(self::publicHost());
    }

    /** Customer portal routes: apex when public surface is live, else legacy portal host. */
    public static function customerHost(): string
    {
        if (self::publicEnabled()) {
            return (string) self::publicHost();
        }

        return self::portalHost();
    }

    public static function portalOnPublicHost(): bool
    {
        return self::publicEnabled();
    }

    public static function appRoutes(Closure $routes): void
    {
        if (self::enabled()) {
            Route::domain(self::appHost())->group($routes);

            return;
        }

        $routes();
    }

    public static function companyRoutes(Closure $routes): void
    {
        $host = self::companyHost();

        if ($host === null) {
            return;
        }

        Route::domain($host)->group($routes);
    }

    public static function portalRoutes(Closure $routes): void
    {
        if (self::publicEnabled()) {
            Route::domain((string) self::publicHost())->group($routes);

            return;
        }

        if (self::enabled()) {
            Route::domain(self::portalHost())->group($routes);

            return;
        }

        $routes();
    }

    public static function publicRoutes(Closure $routes): void
    {
        if (self::publicEnabled()) {
            Route::domain((string) self::publicHost())->group($routes);

            return;
        }

        if (! self::enabled()) {
            $routes();
        }
    }

    public static function urlForHost(string $host, string $path): string
    {
        $path = '/'.ltrim($path, '/');

        return 'https://'.$host.$path;
    }
}
