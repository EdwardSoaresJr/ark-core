<?php

namespace App\Ark\Runtime\Preferences;

use App\Ark\Operations\Workboard\JobBoardLayout;
use Illuminate\Http\Request;

/**
 * Job Board List/Cards preference.
 *
 * Delegates to {@see UiLayoutPreference}. Browser-local storage (tenant + user +
 * module) is the runtime authority; this class remains for SSR / bookmark compat
 * and EncryptCookies registration of the legacy cookie name.
 */
final class JobBoardLayoutPreference
{
    public const COOKIE_NAME = 'ark_job_board_layout';

    public const MODE_AUTO = UiLayoutPreference::MODE_AUTO;

    /**
     * @return array{mode: string, layout: JobBoardLayout|null, explicit: bool}
     */
    public static function resolve(Request $request): array
    {
        $resolved = UiLayoutPreference::resolve($request, UiLayoutModule::JobBoard);

        return [
            'mode' => $resolved['mode'],
            'layout' => $resolved['layout'],
            'explicit' => $resolved['explicit'],
        ];
    }

    public static function queue(JobBoardLayout $layout): void
    {
        UiLayoutPreference::queueLegacyCookie(UiLayoutModule::JobBoard, $layout);
    }
}
