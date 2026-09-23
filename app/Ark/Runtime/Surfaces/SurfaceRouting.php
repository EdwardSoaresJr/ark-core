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

    public static function publicWwwHost(): ?string
    {
        $host = self::publicHost();

        return $host !== null ? 'www.'.$host : null;
    }

    /**
     * Primary public host plus aliases (and preview when set).
     *
     * @return list<string>
     */
    public static function publicHosts(): array
    {
        $hosts = [];
        $primary = self::publicHost();
        if ($primary !== null) {
            $hosts[] = $primary;
        }

        foreach ((array) config('surfaces.public_aliases', []) as $alias) {
            $alias = strtolower(trim((string) $alias));
            if ($alias !== '') {
                $hosts[] = $alias;
            }
        }

        $preview = self::previewHost();
        if ($preview !== null) {
            $hosts[] = $preview;
        }

        return array_values(array_unique($hosts));
    }

    public static function isPublicHost(string $host): bool
    {
        return in_array(strtolower($host), self::publicHosts(), true);
    }

    /** Hosted preview public surface ({slug}-preview.arksms.com) before custom Public Domain. */
    public static function previewHost(): ?string
    {
        $host = config('surfaces.preview');

        return filled($host) ? (string) $host : null;
    }

    /** Company product host - ARK Cloud (marketing + trial + cloud dashboard). */
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
     * Future: app.autorepairkeeper.com - Auth + Cloud dashboard.
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
            foreach (self::publicHosts() as $host) {
                Route::domain($host)->group($routes);
            }

            return;
        }

        if (self::enabled()) {
            Route::domain(self::portalHost())->group($routes);

            return;
        }

        $routes();
    }

    public static function coreOperationalRoutes(Closure $routes): void
    {
        if (! self::enabled()) {
            $routes();

            return;
        }

        Route::domain(self::appHost())->group($routes);

        if (self::publicEnabled()) {
            foreach (self::publicHosts() as $host) {
                if (strcasecmp($host, self::appHost()) === 0) {
                    continue;
                }

                Route::domain($host)->group($routes);
            }

            return;
        }

        if (strcasecmp(self::portalHost(), self::appHost()) !== 0) {
            Route::domain(self::portalHost())->group($routes);
        }
    }

    public static function publicRoutes(Closure $routes): void
    {
        if (self::publicEnabled()) {
            foreach (self::publicHosts() as $host) {
                Route::domain($host)->group($routes);
            }

            return;
        }

        $preview = self::previewHost();
        if (filled($preview)) {
            Route::domain((string) $preview)->group($routes);

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
