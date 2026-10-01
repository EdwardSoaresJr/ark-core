<?php

namespace App\Ark\Platform\Mail;

use App\Ark\Platform\PlatformConnection;

final class ManagedMailGate
{
    public static function platformConnected(): bool
    {
        return PlatformConnection::current()->isConnected();
    }

    public static function platformSend(): bool
    {
        if (! self::platformConnected()) {
            return false;
        }

        return (bool) config('services.ark_platform.mail_send', true);
    }
}
