<?php

namespace App\Ark\Platform\Payments;

use App\Ark\Platform\PlatformConnection;

/**
 * Hosted card capture goes through Platform. Self-host keeps Core Square.
 */
final class ManagedPaymentsGate
{
    public static function platformConnected(): bool
    {
        return PlatformConnection::current()->isConnected();
    }

    public static function platformCapture(): bool
    {
        if (! self::platformConnected()) {
            return false;
        }

        return (bool) config('services.ark_platform.payments_capture', true);
    }
}
