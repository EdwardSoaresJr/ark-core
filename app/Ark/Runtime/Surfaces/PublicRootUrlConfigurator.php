<?php

namespace App\Ark\Runtime\Surfaces;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

final class PublicRootUrlConfigurator
{
    public static function apply(): void
    {
        $configured = rtrim((string) config('app.url'), '/');
        $host = parse_url($configured, PHP_URL_HOST);

        if ($host !== null && ! self::isLocalHost($host)) {
            if (str_starts_with($configured, 'https://')) {
                URL::forceScheme('https');
            }

            return;
        }

        $fallback = self::fallbackRootUrl();

        if ($fallback === null) {
            return;
        }

        URL::forceRootUrl($fallback);
        URL::forceScheme('https');

        if (! app()->runningUnitTests()) {
            Log::warning('APP_URL points at localhost; public URLs use configured domain fallback.', [
                'app_url' => $configured,
                'fallback_root_url' => $fallback,
            ]);
        }
    }

    public static function fallbackRootUrl(): ?string
    {
        $operationsUrl = rtrim((string) config('ark-ecosystem.operations_url'), '/');
        $operationsHost = parse_url($operationsUrl, PHP_URL_HOST);

        if ($operationsHost !== null && ! self::isLocalHost($operationsHost)) {
            return $operationsUrl;
        }

        if (! config('surfaces.enabled')) {
            return null;
        }

        $appDomain = config('surfaces.app');

        if (! filled($appDomain)) {
            return null;
        }

        return 'https://'.(string) $appDomain;
    }

    private static function isLocalHost(string $host): bool
    {
        return in_array(strtolower($host), ['localhost', '127.0.0.1', '::1'], true);
    }
}
