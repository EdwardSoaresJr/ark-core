<?php

namespace App\Ark\Runtime\Preferences;

use App\Ark\Operations\Workboard\JobBoardLayout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * Legacy Board/List cookie. The Job Board is table-first; any view=board|list
 * or cookie value is normalized to list so bookmarks and sessions keep working.
 */
final class JobBoardLayoutPreference
{
    public const COOKIE_NAME = 'ark_job_board_layout';

    public static function resolve(Request $request): JobBoardLayout
    {
        if ($request->filled('view')) {
            self::queue(JobBoardLayout::List);
        }

        return JobBoardLayout::List;
    }

    public static function queue(JobBoardLayout $layout): void
    {
        Cookie::queue(cookie(
            self::COOKIE_NAME,
            JobBoardLayout::List->value,
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
