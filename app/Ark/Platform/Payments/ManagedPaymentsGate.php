<?php

namespace App\Ark\Platform\Payments;

use App\Ark\Platform\PlatformConnection;

class ManagedPaymentsGate
{
    public static function platformConnected(): bool
    {
        return app(self::class)->connected();
    }

    public function connected(): bool
    {
        return PlatformConnection::current()->isConnected();
    }

    public static function platformCapture(): bool
    {
        return app(self::class)->captureEnabled();
    }

    public function captureEnabled(): bool
    {
        if (! $this->connected()) {
            return false;
        }

        return (bool) config('services.ark_platform.payments_capture', true);
    }
}
