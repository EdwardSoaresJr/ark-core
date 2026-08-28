<?php

namespace App\Ark\Operations\Leads;

use Illuminate\Support\Facades\Cache;

/**
 * Per-viewer popup suppression for website lead interrupts.
 */
final class WebsiteLeadInterruptDismissal
{
    public static function cacheKey(int $userId): string
    {
        return "leads:website-interrupt-dismissed:{$userId}";
    }

    public function dismiss(int $userId, int $leadId): void
    {
        if ($userId <= 0 || $leadId <= 0) {
            return;
        }

        $dismissed = Cache::get(self::cacheKey($userId), []);

        if (! is_array($dismissed)) {
            $dismissed = [];
        }

        $dismissed[$leadId] = true;

        Cache::put(self::cacheKey($userId), $dismissed, now()->addHours(8));
    }

    public function isDismissed(int $userId, int $leadId): bool
    {
        if ($userId <= 0 || $leadId <= 0) {
            return false;
        }

        $dismissed = Cache::get(self::cacheKey($userId), []);

        return is_array($dismissed) && ($dismissed[$leadId] ?? false) === true;
    }
}
