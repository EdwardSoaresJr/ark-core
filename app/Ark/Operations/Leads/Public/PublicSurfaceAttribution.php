<?php

namespace App\Ark\Operations\Leads\Public;

use Illuminate\Http\Request;

final class PublicSurfaceAttribution
{
    public static function fromRequest(Request $request): string
    {
        $referer = (string) $request->headers->get('referer', '');

        if ($referer === '') {
            return 'direct';
        }

        $host = strtolower((string) parse_url($referer, PHP_URL_HOST));

        if ($host === '') {
            return 'direct';
        }

        if (self::hostMatches($host, ['google.', 'g.page', 'maps.google.', 'www.google.'])) {
            return 'google';
        }

        if (self::hostMatches($host, ['facebook.', 'fb.', 'm.facebook.', 'l.facebook.', 'instagram.'])) {
            return 'facebook';
        }

        if (self::hostMatches($host, ['bing.', 'duckduckgo.', 'yahoo.'])) {
            return 'search';
        }

        return 'referral';
    }

    /**
     * @param  list<string>  $needles
     */
    private static function hostMatches(string $host, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($host === rtrim($needle, '.') || str_contains($host, $needle)) {
                return true;
            }
        }

        return false;
    }
}
