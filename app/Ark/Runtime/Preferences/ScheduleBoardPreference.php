<?php

namespace App\Ark\Runtime\Preferences;

use App\Ark\Operations\Appointments\ScheduleBoardView;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Schema;

final class ScheduleBoardPreference
{
    public const COOKIE_NAME = 'ark_schedule_board_view';

    public static function resolve(Request $request, ?User $user = null): ScheduleBoardView
    {
        if ($request->filled('view')) {
            return ScheduleBoardView::parse((string) $request->string('view'));
        }

        $user ??= $request->user();
        if ($user instanceof User && filled($user->schedule_board_view)) {
            return ScheduleBoardView::parse((string) $user->schedule_board_view);
        }

        $cookie = $request->cookie(self::COOKIE_NAME);

        return ScheduleBoardView::parse(is_string($cookie) ? $cookie : null);
    }

    public static function persist(User $user, ScheduleBoardView $view): void
    {
        if (Schema::hasColumn('users', 'schedule_board_view')) {
            $user->forceFill(['schedule_board_view' => $view->value])->save();
        }

        Cookie::queue(cookie(
            self::COOKIE_NAME,
            $view->value,
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
