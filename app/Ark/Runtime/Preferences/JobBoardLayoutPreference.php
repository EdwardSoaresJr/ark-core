<?php

namespace App\Ark\Runtime\Preferences;

use App\Ark\Operations\Workboard\JobBoardLayout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * Job Board Board/List layout. Cookie only — Board stays the default until product
 * acceptance promotes List. Query ?view=list|board wins for the request and refreshes the cookie.
 */
final class JobBoardLayoutPreference
{
    public const COOKIE_NAME = 'ark_job_board_layout';

    public static function resolve(Request $request): JobBoardLayout
    {
        if ($request->filled('view')) {
            $layout = JobBoardLayout::parse((string) $request->string('view'));
            self::queue($layout);

            return $layout;
        }

        $cookie = $request->cookie(self::COOKIE_NAME);

        return JobBoardLayout::parse(is_string($cookie) ? $cookie : null);
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
