<?php

namespace App\Ark\Platform;

/**
 * Public URLs for shop capabilities — always derived from SHOP_BASE_URL.
 *
 * @see docs/platform/shop-identity-v1.md
 */
final class ShopBaseUrl
{
    public static function origin(): string
    {
        return rtrim((string) config('shop.base_url'), '/');
    }

    public static function host(): string
    {
        $host = parse_url(self::origin(), PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : 'localhost';
    }

    public static function capability(string $path): string
    {
        $path = ltrim($path, '/');

        return $path === '' ? self::origin() : self::origin().'/'.$path;
    }

    public static function voice(string $path = ''): string
    {
        $path = ltrim($path, '/');

        return self::capability($path === '' ? 'voice' : 'voice/'.$path);
    }
}
