<?php

namespace App\Ark\Platform;

use App\Ark\Runtime\Surfaces\SurfaceRouting;

/**
 * Browser origin for Core operational HTTP (staff workspace and tokenized customer links).
 *
 * Independent of the shop website domain, Fabric transport, and mobile API discovery.
 */
final class CoreApplicationOrigin
{
    public static function host(): string
    {
        if (SurfaceRouting::enabled()) {
            return SurfaceRouting::appHost();
        }

        return ShopBaseUrl::host();
    }

    public static function origin(): string
    {
        $scheme = parse_url(ShopBaseUrl::origin(), PHP_URL_SCHEME);

        if (! is_string($scheme) || ! in_array(strtolower($scheme), ['http', 'https'], true)) {
            $scheme = 'https';
        }

        return strtolower($scheme).'://'.self::host();
    }

    public static function url(string $path): string
    {
        return self::origin().'/'.ltrim($path, '/');
    }
}
