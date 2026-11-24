<?php

namespace App\Ark\Runtime\Preferences;

use App\Ark\Operations\Workboard\JobBoardLayout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * Shared List/Cards preference contract.
 *
 * Authority (browser): localStorage keyed by tenant + user + module
 * (`ark.ui.layout.v1:{tenant}:{user}:{module}`). See
 * `resources/js/ark-ui-layout-preference.js` and
 * `docs/engineering/TABLER_LAYOUT_PREFERENCE_MIGRATION.md`.
 *
 * Server resolve is for first paint / bookmarks only:
 * `?view=` (compat) → legacy module cookie → auto.
 * Navigation must not emit `?view=`; JS must not rewrite the URL to add it.
 *
 * Dual-write: when the server accepts `?view=`, it still queues the legacy
 * cookie so the next SSR paints correctly until the browser store is warm.
 */
final class UiLayoutPreference
{
    public const MODE_AUTO = 'auto';

    public const STORAGE_PREFIX = 'ark.ui.layout.v1';

    /**
     * @return array{
     *     mode: string,
     *     layout: JobBoardLayout|null,
     *     explicit: bool,
     *     module: UiLayoutModule,
     *     scope: array{tenant: string, user: string}
     * }
     */
    public static function resolve(Request $request, UiLayoutModule $module): array
    {
        $scope = UiPreferenceScope::forRequest($request);

        if ($request->filled('view')) {
            $layout = JobBoardLayout::parse((string) $request->string('view'));
            self::queueLegacyCookie($module, $layout);

            return [
                'mode' => $layout->value,
                'layout' => $layout,
                'explicit' => true,
                'module' => $module,
                'scope' => $scope,
            ];
        }

        $legacyName = $module->legacyCookieName();
        if (is_string($legacyName)) {
            $cookie = $request->cookie($legacyName);
            $fromCookie = is_string($cookie) ? JobBoardLayout::tryParse($cookie) : null;
            if ($fromCookie instanceof JobBoardLayout) {
                return [
                    'mode' => $fromCookie->value,
                    'layout' => $fromCookie,
                    'explicit' => true,
                    'module' => $module,
                    'scope' => $scope,
                ];
            }
        }

        return [
            'mode' => self::MODE_AUTO,
            'layout' => null,
            'explicit' => false,
            'module' => $module,
            'scope' => $scope,
        ];
    }

    public static function queueLegacyCookie(UiLayoutModule $module, JobBoardLayout $layout): void
    {
        $name = $module->legacyCookieName();
        if ($name === null) {
            return;
        }

        Cookie::queue(cookie(
            $name,
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
     * Cookie names that first-party layout JS may read (legacy migration).
     *
     * @return list<string>
     */
    public static function legacyCookieNames(): array
    {
        $names = [];
        foreach (UiLayoutModule::cases() as $module) {
            $name = $module->legacyCookieName();
            if (is_string($name)) {
                $names[] = $name;
            }
        }

        return array_values(array_unique($names));
    }
}
