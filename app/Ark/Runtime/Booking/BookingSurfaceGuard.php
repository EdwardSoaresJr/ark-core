<?php

namespace App\Ark\Runtime\Booking;

use RuntimeException;

/**
 * Fail closed when Public Core would absorb a marketing host that still owns /book.
 */
final class BookingSurfaceGuard
{
    public static function assertCutoverSafe(): void
    {
        if (! BookingSurface::enforce()) {
            return;
        }

        $claimed = BookingSurface::claimedProtectedHosts();

        if ($claimed === []) {
            return;
        }

        if (BookingSurface::isConfigured()) {
            return;
        }

        $hosts = implode(', ', $claimed);

        throw new RuntimeException(
            "Booking surface required: this Core claims protected marketing host(s) [{$hosts}] ".
            'but BOOKING_SURFACE_BASE_URL is empty. Keep lugsnplugs.com on the booking runtime, '.
            'or set BOOKING_SURFACE_BASE_URL to that origin so /book can redirect. '.
            'Do not attach marketing FQDNs to Public Core without a bridge.'
        );
    }
}
