<?php

namespace App\Ark\Platform\Communications;

use App\Ark\Platform\PlatformConnection;

/**
 * Track C gates for managed Communications on Hosted installations.
 */
final class ManagedCommunicationsGate
{
    public static function platformConnected(): bool
    {
        return PlatformConnection::current()->isConnected();
    }

    /** Platform owns conversation/message state. */
    public static function platformAuthority(): bool
    {
        if (! self::platformConnected()) {
            return false;
        }

        return (bool) config('services.ark_platform.communications_authority', true);
    }

    /** Native inbox data plane reads Platform APIs. */
    public static function platformInbox(): bool
    {
        return self::platformAuthority()
            && (bool) config('services.ark_platform.communications_inbox', true);
    }

    /** Outbound SMS goes through Platform conversation.send. */
    public static function platformSend(): bool
    {
        return self::platformAuthority()
            && (bool) config('services.ark_platform.communications_send', true);
    }

    /**
     * Compat mirror into Core ConversationMessage — retired after Gate 3.
     * Hosted + Platform authority: always false. Self-host (!platformAuthority): N/A.
     */
    public static function coreMirrorEnabled(): bool
    {
        if (! self::platformAuthority()) {
            return true;
        }

        return (bool) config('services.ark_platform.communications_core_mirror', false);
    }

    /**
     * Stage 11: never resurrect Core inbox as Hosted managed Communications authority.
     */
    public static function allowLegacyInboxFallback(): bool
    {
        return false;
    }

    /** Reject Core Twilio Messaging webhooks when Platform is the ingress. */
    public static function rejectCoreTwilioMessagingWebhooks(): bool
    {
        return self::platformAuthority()
            && (bool) config('services.ark_platform.reject_core_twilio_messaging_webhooks', false);
    }
}
