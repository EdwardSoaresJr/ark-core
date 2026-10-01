<?php

namespace App\Ark\Operations\Portal;

use Illuminate\Http\Request;

final class PortalIntendedUrl
{
    public const SESSION_KEY = 'portal_intended_url';

    public static function store(Request $request, mixed $return): void
    {
        $validated = self::validate(is_string($return) ? $return : null);

        if ($validated === null) {
            return;
        }

        $request->session()->put(self::SESSION_KEY, $validated);
    }

    public static function pull(Request $request): ?string
    {
        $url = $request->session()->pull(self::SESSION_KEY);

        return is_string($url) ? self::validate($url) : null;
    }

    public static function validate(?string $return): ?string
    {
        if (! filled($return)) {
            return null;
        }

        if (str_contains($return, '//') || str_contains($return, ':')) {
            return null;
        }

        if (strlen($return) > 512) {
            return null;
        }

        // Portal records + Book Service recognition return (Customer Recognition Layer).
        if ($return === '/book' || str_starts_with($return, '/book?')) {
            return $return;
        }

        if (! str_starts_with($return, '/portal/')) {
            return null;
        }

        return $return;
    }
}
