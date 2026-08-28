<?php

declare(strict_types=1);

namespace App\Ark\Operations\Printing;

final class RasterDpiResolver
{
    public function resolve(?string $userAgent = null): int
    {
        $shop = ShopPrintingSettings::shopKeyTagRasterDpiOverride();
        if ($shop !== null) {
            return $shop;
        }

        if (self::userAgentLooksLikeMac($userAgent)) {
            return 300;
        }

        $cfg = (int) config('printing.key_tag.default_dpi', 300);

        return $cfg > 0 ? $cfg : 300;
    }

    public static function userAgentLooksLikeMac(?string $userAgent): bool
    {
        if ($userAgent === null || $userAgent === '') {
            return false;
        }

        $ua = strtolower($userAgent);

        return str_contains($ua, 'mac os x')
            || str_contains($ua, 'macintosh')
            || str_contains($ua, 'iphone')
            || str_contains($ua, 'ipad')
            || str_contains($ua, 'ipod');
    }
}
