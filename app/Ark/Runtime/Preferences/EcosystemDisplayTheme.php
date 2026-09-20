<?php

namespace App\Ark\Runtime\Preferences;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

final class EcosystemDisplayTheme
{
    public const COOKIE_NAME = 'ark_display_theme';

    public static function cookieName(): string
    {
        return self::COOKIE_NAME;
    }

    public static function cookieDomain(): ?string
    {
        $domain = config('ark-ecosystem.cookie_domain');

        return is_string($domain) && $domain !== '' ? $domain : null;
    }

    public static function queueForUser(DisplayTheme $theme): void
    {
        Cookie::queue(self::cookie($theme->value));
    }

    public static function attachToResponse(Response $response, DisplayTheme $theme): void
    {
        $response->headers->setCookie(self::cookie($theme->value));
    }

    public static function resolveFromRequest(Request $request): DisplayTheme
    {
        $raw = $request->cookie(self::COOKIE_NAME);

        return DisplayTheme::tryFromStored(is_string($raw) ? $raw : null);
    }

    public static function shouldUseDark(DisplayTheme $theme, bool $prefersDark): bool
    {
        return $theme->resolvesToDark($prefersDark);
    }

    private static function cookie(string $value): \Symfony\Component\HttpFoundation\Cookie
    {
        return cookie(
            self::COOKIE_NAME,
            $value,
            minutes: 60 * 24 * 365,
            path: '/',
            domain: self::cookieDomain(),
            secure: (bool) config('session.secure', false),
            httpOnly: false,
            raw: false,
            sameSite: 'lax',
        );
    }
}
