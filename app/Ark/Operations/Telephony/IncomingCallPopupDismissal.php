<?php

namespace App\Ark\Operations\Telephony;

use Illuminate\Support\Facades\Cache;

/**
 * Per-viewer popup suppression for actively live calls.
 *
 * Dismiss closes the interrupt for one staff member without queue authority.
 */
class IncomingCallPopupDismissal
{
    public static function cacheKey(int $userId): string
    {
        return "telephony:popup-dismissed:{$userId}";
    }

    public function dismiss(int $userId, int $callSessionId): void
    {
        if ($userId <= 0 || $callSessionId <= 0) {
            return;
        }

        $dismissed = Cache::get(self::cacheKey($userId), []);

        if (! is_array($dismissed)) {
            $dismissed = [];
        }

        $dismissed[$callSessionId] = true;

        Cache::put(self::cacheKey($userId), $dismissed, now()->addHours(8));
    }

    public function isDismissed(int $userId, int $callSessionId): bool
    {
        if ($userId <= 0 || $callSessionId <= 0) {
            return false;
        }

        $dismissed = Cache::get(self::cacheKey($userId), []);

        return is_array($dismissed) && ($dismissed[$callSessionId] ?? false) === true;
    }
}
