<?php

namespace App\Ark\Growth\PublicSurface;

use App\Ark\Runtime\Surfaces\SurfaceRouting;
use Illuminate\Http\Request;

/**
 * Public marketing host URL authority — transport only, not SEO metadata.
 */
final class PublicMarketingUrl
{
    public static function baseUrl(): string
    {
        $host = SurfaceRouting::publicHost();

        if (filled($host)) {
            return 'https://'.$host;
        }

        return rtrim((string) config('app.url'), '/');
    }

    public static function absolute(string $path): string
    {
        $path = '/'.ltrim($path, '/');

        if ($path === '//') {
            $path = '/';
        }

        return rtrim(self::baseUrl(), '/').($path === '/' ? '/' : $path);
    }

    public static function absoluteIfRelative(?string $url): ?string
    {
        if (! is_string($url) || trim($url) === '') {
            return null;
        }

        $url = trim($url);

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        return rtrim(self::baseUrl(), '/').'/'.ltrim($url, '/');
    }

    public static function isPublicMarketingHost(Request $request): bool
    {
        if (SurfaceRouting::publicEnabled()) {
            return $request->getHost() === SurfaceRouting::publicHost();
        }

        return false;
    }

    public static function servesPublicSeo(Request $request): bool
    {
        if (self::isPublicMarketingHost($request)) {
            return true;
        }

        return ! SurfaceRouting::enabled();
    }

    public static function opensInNewTab(?string $href): bool
    {
        if (! is_string($href) || trim($href) === '') {
            return false;
        }

        $href = trim($href);

        if (! str_starts_with($href, 'http://') && ! str_starts_with($href, 'https://')) {
            return false;
        }

        $host = self::normalizeHost(parse_url($href, PHP_URL_HOST));

        if ($host === null) {
            return false;
        }

        $internalHosts = array_values(array_filter(array_unique([
            self::normalizeHost(parse_url((string) config('app.url'), PHP_URL_HOST)),
            self::normalizeHost(parse_url(self::baseUrl(), PHP_URL_HOST)),
            SurfaceRouting::enabled() ? self::normalizeHost(SurfaceRouting::appHost()) : null,
            self::normalizeHost(SurfaceRouting::publicHost()),
        ])));

        return ! in_array($host, $internalHosts, true);
    }

    private static function normalizeHost(mixed $host): ?string
    {
        if (! is_string($host) || trim($host) === '') {
            return null;
        }

        return strtolower(trim($host));
    }
}
