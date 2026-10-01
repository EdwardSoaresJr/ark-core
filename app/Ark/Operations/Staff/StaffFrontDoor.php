<?php

namespace App\Ark\Operations\Staff;

use App\Ark\Runtime\Authorization\ArkCapability;
use App\Models\User;

final class StaffFrontDoor
{
    public const STAFF_SHELL_PERMISSION = 'production.access|operations.access';

    public static function landingRouteName(?User $user = null): string
    {
        return 'operations.today';
    }

    public static function landingUrl(?User $user = null): string
    {
        return route('operations.today');
    }

    public static function usesAdvisorWorkSurface(?User $user = null): bool
    {
        $user ??= auth()->user();

        return $user instanceof User
            && $user->can(ArkCapability::OperationsAccess->value);
    }

    public static function canUseStaffShell(?User $user = null): bool
    {
        $user ??= auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        return $user->can(ArkCapability::ProductionAccess->value)
            || $user->can(ArkCapability::OperationsAccess->value);
    }

    public static function postLoginRedirectUrl(User $user): string
    {
        $landing = self::landingUrl($user);
        $intended = session()->pull('url.intended');

        if (! is_string($intended) || $intended === '') {
            return $landing;
        }

        if (! self::canAccessStaffPath($intended, $user)) {
            return $landing;
        }

        return $intended;
    }

    public static function canAccessStaffPath(string $url, ?User $user = null): bool
    {
        $user ??= auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        $path = parse_url($url, PHP_URL_PATH) ?? '';
        $path = '/'.ltrim($path, '/');

        if (self::pathRequiresOperationsAccess($path)) {
            return $user->can(ArkCapability::OperationsAccess->value);
        }

        return self::canUseStaffShell($user);
    }

    private static function pathRequiresOperationsAccess(string $path): bool
    {
        if (
            $path === '/app/today'
            || $path === '/app/communications/attention'
            || $path === '/app/communications/inbox'
        ) {
            return false;
        }

        if ($path === '/app') {
            return true;
        }

        return str_starts_with($path, '/app/communications')
            || str_starts_with($path, '/app/work/')
            || str_starts_with($path, '/app/work?')
            || str_starts_with($path, '/app/appointments');
    }
}
