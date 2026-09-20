<?php

namespace App\Ark\Runtime\Http;

final class SameOriginUrl
{
    public static function isAllowed(?string $url): bool
    {
        return is_string($url) && $url !== '' && str_starts_with($url, url('/'));
    }

    public static function resolve(?string $preferred, string $fallback): string
    {
        if (self::isAllowed($preferred)) {
            return $preferred;
        }

        return $fallback;
    }
}
