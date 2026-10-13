<?php

namespace App\Ark\Platform\Mail;

use App\Ark\Platform\PlatformConnection;

class ManagedMailGate
{
    public static function platformConnected(): bool
    {
        return app(self::class)->connected();
    }

    public function connected(): bool
    {
        return PlatformConnection::current()->isConnected();
    }

    public static function platformSend(): bool
    {
        return app(self::class)->sendEnabled();
    }

    public function sendEnabled(): bool
    {
        if (! $this->connected()) {
            return false;
        }

        return (bool) config('services.ark_platform.mail_send', true);
    }
}
