<?php

namespace App\Ark\Platform\Communications;

use App\Ark\Platform\PlatformConnection;

class ManagedCommunicationsGate
{
    public static function platformConnected(): bool
    {
        return app(self::class)->connected();
    }

    public function connected(): bool
    {
        return PlatformConnection::current()->isConnected();
    }

    public static function platformAuthority(): bool
    {
        return app(self::class)->authority();
    }

    public function authority(): bool
    {
        if (! $this->connected()) {
            return false;
        }

        return (bool) config('services.ark_platform.communications_authority', true);
    }

    public static function platformInbox(): bool
    {
        return app(self::class)->inbox();
    }

    public function inbox(): bool
    {
        return $this->authority()
            && (bool) config('services.ark_platform.communications_inbox', true);
    }

    public static function platformSend(): bool
    {
        return app(self::class)->sendEnabled();
    }

    public function sendEnabled(): bool
    {
        return $this->authority()
            && (bool) config('services.ark_platform.communications_send', true);
    }

    public static function coreMirrorEnabled(): bool
    {
        return app(self::class)->coreMirror();
    }

    public function coreMirror(): bool
    {
        if (! $this->authority()) {
            return true;
        }

        return (bool) config('services.ark_platform.communications_core_mirror', false);
    }

    public static function allowLegacyInboxFallback(): bool
    {
        return app(self::class)->legacyInbox();
    }

    public function legacyInbox(): bool
    {
        return false;
    }

    public static function rejectCoreTwilioMessagingWebhooks(): bool
    {
        return app(self::class)->rejectCoreWebhooks();
    }

    public function rejectCoreWebhooks(): bool
    {
        return $this->authority()
            && (bool) config('services.ark_platform.reject_core_twilio_messaging_webhooks', false);
    }
}
