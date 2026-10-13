<?php

namespace App\Ark\Platform\Parts;

use App\Ark\Platform\PlatformConnection;

class ManagedPartsGate
{
    public static function platformConnected(): bool
    {
        return app(self::class)->connected();
    }

    public function connected(): bool
    {
        return PlatformConnection::current()->isConnected();
    }

    public static function platformCatalog(): bool
    {
        return app(self::class)->catalogEnabled();
    }

    public function catalogEnabled(): bool
    {
        if (! $this->connected()) {
            return false;
        }

        return (bool) config('services.ark_platform.parts_catalog', true);
    }
}
