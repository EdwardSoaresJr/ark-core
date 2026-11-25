<?php

namespace App\Ark\Runtime\Preferences;

use App\Ark\Operations\Workboard\JobBoardLayout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * Shared List/Cards preference contract.
 *
 * Job Board authority: authenticated `users.job_board_view` via
 * {@see JobBoardLayoutPreference}. Browser localStorage / legacy cookie are
 * mirrors and one-time migration sources only.
 *
 * Collection modules (customers / vehicles / repair_orders) still use the
 * browser-local contract until their DB-backed slice ships — do not invent a
 * second store for those modules.
 *
 * `?view=` remains a one-shot bookmark for Job Board (persisted then stripped).
 * Navigation must not emit `?view=`.
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

        if ($module === UiLayoutModule::JobBoard) {
            $resolved = JobBoardLayoutPreference::resolve($request);

            return [
                'mode' => $resolved['mode'],
                'layout' => $resolved['layout'],
                'explicit' => $resolved['explicit'],
                'module' => $module,
                'scope' => $scope,
            ];
        }

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
