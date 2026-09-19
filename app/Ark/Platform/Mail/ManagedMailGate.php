<?php

namespace App\Ark\Platform\Mail;

use App\Ark\Platform\PlatformConnection;

/**
 * Hosted transactional mail goes through Platform. Self-host keeps Laravel/Postmark.
 */
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
