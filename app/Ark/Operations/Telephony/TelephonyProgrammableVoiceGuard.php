<?php

namespace App\Ark\Operations\Telephony;

/**
 * Twilio Programmable Voice is the sole voice transport.
 */
final class TelephonyProgrammableVoiceGuard
{
    public static function isActive(): bool
    {
        return true;
    }

    public static function hangupTwiml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><Response><Hangup/></Response>';
    }
}
