<?php

namespace App\Ark\Runtime\Preferences;

use App\Ark\Operations\Workboard\JobBoardLayout;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Job Board List/Cards preference.
 *
 * Authority: authenticated user's `job_board_view` column (per shop tenant).
 * Legacy `ark_job_board_layout` cookie and browser localStorage are mirrors /
 * one-time migration sources only — they must not override a saved account value.
 *
 * Missing or invalid values resolve to Cards. New users default to Cards.
 */
final class JobBoardLayoutPreference
{
    public const COOKIE_NAME = 'ark_job_board_layout';

    public const COLUMN = 'job_board_view';

    /**
     * @deprecated Prefer checking explicit + layout. Kept for callers that compared MODE_AUTO.
     */
    public const MODE_AUTO = UiLayoutPreference::MODE_AUTO;

    /**
     * @return array{mode: string, layout: JobBoardLayout, explicit: bool}
     */
    public static function resolve(Request $request, ?User $user = null): array
    {
        $user ??= $request->user();

        if ($request->filled('view')) {
            $layout = JobBoardLayout::parse((string) $request->string('view'));
            if ($user instanceof User) {
                self::persist($user, $layout);
            } else {
                self::queue($layout);
            }

            return self::payload($layout, explicit: true);
        }

        $fromUser = self::storedLayout($user);
        if ($fromUser instanceof JobBoardLayout) {
            self::queue($fromUser);

            return self::payload($fromUser, explicit: true);
        }

        $cookie = $request->cookie(self::COOKIE_NAME);
        $fromCookie = is_string($cookie) ? JobBoardLayout::tryParse($cookie) : null;
        if ($fromCookie instanceof JobBoardLayout) {
            if ($user instanceof User) {
                self::persist($user, $fromCookie);
            } else {
                self::queue($fromCookie);
            }

            return self::payload($fromCookie, explicit: true);
        }

        return self::payload(JobBoardLayout::default(), explicit: false);
    }

    public static function persist(User $user, JobBoardLayout $layout): void
    {
        if (Schema::hasColumn('users', self::COLUMN)) {
            $user->forceFill([self::COLUMN => $layout->value])->save();
        }

        self::queue($layout);
    }

    public static function queue(JobBoardLayout $layout): void
    {
        UiLayoutPreference::queueLegacyCookie(UiLayoutModule::JobBoard, $layout);
    }

    public static function storedLayout(?User $user): ?JobBoardLayout
    {
        if (! $user instanceof User) {
            return null;
        }

        if (! Schema::hasColumn('users', self::COLUMN)) {
            return null;
        }

        $raw = $user->getAttribute(self::COLUMN);
        if (! is_string($raw) || $raw === '') {
            return null;
        }

        return JobBoardLayout::tryParse($raw);
    }

    /**
     * @return array{mode: string, layout: JobBoardLayout, explicit: bool}
     */
    private static function payload(JobBoardLayout $layout, bool $explicit): array
    {
        return [
            'mode' => $layout->value,
            'layout' => $layout,
            'explicit' => $explicit,
        ];
    }
}
