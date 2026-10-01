<?php

namespace App\Ark\Operations\Portal;

use Illuminate\Support\Facades\Cache;

/**
 * Per-viewer popup suppression for portal customer activity interrupts.
 */
final class PortalCustomerActivityInterruptDismissal
{
    public static function cacheKey(int $userId): string
    {
        return "portal:customer-activity-interrupt-dismissed:{$userId}";
    }

    public function dismiss(int $userId, string $portalInterruptKey): void
    {
        if ($userId <= 0 || $portalInterruptKey === '') {
            return;
        }

        $dismissed = Cache::get(self::cacheKey($userId), []);

        if (! is_array($dismissed)) {
            $dismissed = [];
        }

        $dismissed[$portalInterruptKey] = true;

        Cache::put(self::cacheKey($userId), $dismissed, now()->addHours(8));
    }

    public function isDismissed(int $userId, string $portalInterruptKey): bool
    {
        if ($userId <= 0 || $portalInterruptKey === '') {
            return false;
        }

        $dismissed = Cache::get(self::cacheKey($userId), []);

        return is_array($dismissed) && ($dismissed[$portalInterruptKey] ?? false) === true;
    }
}
