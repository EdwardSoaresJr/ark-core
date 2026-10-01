<?php

namespace App\Ark\Platform\Parts;

use App\Ark\Platform\PlatformConnection;

/**
 * Hosted catalog goes through Platform. Self-host keeps Core PartsTech HTTP.
 */
final class ManagedPartsGate
{
    public static function platformConnected(): bool
    {
        return PlatformConnection::current()->isConnected();
    }

    public static function platformCatalog(): bool
    {
        if (! self::platformConnected()) {
            return false;
        }

        return (bool) config('services.ark_platform.parts_catalog', true);
    }
}
