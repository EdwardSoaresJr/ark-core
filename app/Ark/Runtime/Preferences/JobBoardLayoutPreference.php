<?php

namespace App\Ark\Runtime\Preferences;

use App\Ark\Operations\Workboard\JobBoardLayout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * Per-browser Job Board List/Cards preference (cookie).
 * Shop-default storage is a follow-up; no migration in this pass.
 *
 * Resolution: ?view= → cookie → auto (List on desktop, Cards on mobile in the UI).
 */
final class JobBoardLayoutPreference
{
    public const COOKIE_NAME = 'ark_job_board_layout';

    public const MODE_AUTO = 'auto';

    /**
     * @return array{mode: string, layout: JobBoardLayout|null, explicit: bool}
     */
    public static function resolve(Request $request): array
    {
        if ($request->filled('view')) {
            $layout = JobBoardLayout::parse((string) $request->string('view'));
            self::queue($layout);

            return [
                'mode' => $layout->value,
                'layout' => $layout,
                'explicit' => true,
            ];
        }

        $cookie = $request->cookie(self::COOKIE_NAME);
        $fromCookie = is_string($cookie) ? JobBoardLayout::tryParse($cookie) : null;

        if ($fromCookie instanceof JobBoardLayout) {
            return [
                'mode' => $fromCookie->value,
                'layout' => $fromCookie,
                'explicit' => true,
            ];
        }

        return [
            'mode' => self::MODE_AUTO,
            'layout' => null,
            'explicit' => false,
        ];
    }

    public static function queue(JobBoardLayout $layout): void
    {
        Cookie::queue(cookie(
            self::COOKIE_NAME,
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
}
