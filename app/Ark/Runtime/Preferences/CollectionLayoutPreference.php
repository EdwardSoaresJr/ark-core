<?php

namespace App\Ark\Runtime\Preferences;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * Shared List/Cards preference resolver for record indexes.
 *
 * Resolution: ?view= → module cookie → auto (List on desktop, Cards on phone/tablet in the UI).
 * Does not share Job Board's cookie; later preference storage can replace both.
 *
 * @return array{mode: string, layout: CollectionLayout|null, explicit: bool, module: CollectionLayoutModule}
 */
final class CollectionLayoutPreference
{
    public const MODE_AUTO = 'auto';

    /**
     * @return array{mode: string, layout: CollectionLayout|null, explicit: bool, module: CollectionLayoutModule}
     */
    public static function resolve(Request $request, CollectionLayoutModule $module): array
    {
        if ($request->filled('view')) {
            $layout = CollectionLayout::parse((string) $request->string('view'));
            self::queue($module, $layout);

            return [
                'mode' => $layout->value,
                'layout' => $layout,
                'explicit' => true,
                'module' => $module,
            ];
        }

        $cookie = $request->cookie($module->cookieName());
        $fromCookie = is_string($cookie) ? CollectionLayout::tryParse($cookie) : null;

        if ($fromCookie instanceof CollectionLayout) {
            return [
                'mode' => $fromCookie->value,
                'layout' => $fromCookie,
                'explicit' => true,
                'module' => $module,
            ];
        }

        return [
            'mode' => self::MODE_AUTO,
            'layout' => null,
            'explicit' => false,
            'module' => $module,
        ];
    }

    public static function queue(CollectionLayoutModule $module, CollectionLayout $layout): void
    {
        Cookie::queue(cookie(
            $module->cookieName(),
            $layout->value,
            minutes: 60 * 24 * 365,
            path: '/',
            domain: EcosystemDisplayTheme::cookieDomain(),
            secure: (bool) config('session.secure', false),
            httpOnly: false,
            raw: false,
            sameSite: 'lax',
        ));
    }

    /**
     * Cookie names that must stay readable by first-party layout JS.
     *
     * @return list<string>
     */
    public static function cookieNames(): array
    {
        return array_map(
            static fn (CollectionLayoutModule $module): string => $module->cookieName(),
            CollectionLayoutModule::cases(),
        );
    }
}
