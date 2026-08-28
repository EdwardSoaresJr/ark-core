<?php

namespace App\Ark\Operations\Telephony;

/**
 * Desk-phone feature codes (*43 echo, *97 voicemail, **101 pickup, etc.).
 *
 * These are shop-internal dial patterns — not customer communications.
 *
 * @see infra/coolify/asterisk/feature-codes.md
 */
final class TelephonyFeatureCodeDial
{
    public static function isFeatureCodeDial(?string $dial): bool
    {
        $dial = trim((string) $dial);

        if ($dial === '') {
            return false;
        }

        if ($dial === '##' || $dial === '*2') {
            return true;
        }

        return preg_match('/^\*+\d+$/', $dial) === 1;
    }

    public static function shouldIgnoreIncoming(IncomingCallPayload $payload): bool
    {
        return self::matchesDial($payload->toNumber)
            || self::matchesDial($payload->normalizedTo)
            || self::matchesDial(data_get($payload->rawPayload, 'asterisk_called_extension'))
            || self::matchesDial(data_get($payload->rawPayload, 'called_extension'))
            || self::matchesDial(data_get($payload->rawPayload, 'extension'));
    }

    public static function isFeatureCodeSession(CallSession $session): bool
    {
        return self::matchesDial($session->to_number)
            || self::matchesDial($session->normalized_to)
            || self::matchesDial(data_get($session->raw_payload, 'asterisk_called_extension'))
            || self::matchesDial(data_get($session->raw_payload, 'called_extension'))
            || self::matchesDial(data_get($session->raw_payload, 'extension'));
    }

    private static function matchesDial(mixed $dial): bool
    {
        return is_string($dial) && self::isFeatureCodeDial($dial);
    }
}
