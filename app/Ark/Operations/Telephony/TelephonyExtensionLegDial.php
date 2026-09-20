<?php

namespace App\Ark\Operations\Telephony;

use App\Ark\Operations\PhoneNumber;
use Illuminate\Support\Facades\Cache;

/**
 * Asterisk emits a separate AMI channel per extension leg. Those legs often
 * report the desk extension as caller ID — not the customer on the trunk.
 */
final class TelephonyExtensionLegDial
{
    public static function shouldIgnoreIncoming(IncomingCallPayload $payload): bool
    {
        if (TelephonyFeatureCodeDial::shouldIgnoreIncoming($payload)) {
            return true;
        }

        $fromDigits = self::callerDigits($payload->normalizedFrom, $payload->fromNumber);
        $toDigits = self::callerDigits($payload->normalizedTo, $payload->toNumber);

        if (self::isDeskRingGhostLeg($fromDigits, $toDigits)) {
            return true;
        }

        if ($payload->direction === CallSessionDirection::Inbound) {
            return $fromDigits !== null && self::isShopExtension($fromDigits);
        }

        return self::isDeskCompanionCustomerLeg($fromDigits, $toDigits);
    }

    public static function isExtensionLegSession(CallSession $session): bool
    {
        return self::isInboxGhostSession($session);
    }

    public static function isInboxGhostSession(CallSession $session): bool
    {
        if ($session->customer_id !== null) {
            return false;
        }

        if (TelephonyFeatureCodeDial::isFeatureCodeSession($session)) {
            return true;
        }

        $fromDigits = self::callerDigits((string) $session->normalized_from, (string) $session->from_number);
        $toDigits = self::callerDigits((string) $session->normalized_to, (string) $session->to_number);

        if (self::isDeskRingGhostLeg($fromDigits, $toDigits)) {
            return true;
        }

        if ($session->direction === CallSessionDirection::Inbound
            && $fromDigits !== null
            && self::isShopExtension($fromDigits)) {
            return true;
        }

        return self::isDeskCompanionCustomerLeg($fromDigits, $toDigits);
    }

    private static function isDeskCompanionCustomerLeg(?string $fromDigits, ?string $toDigits): bool
    {
        return $fromDigits !== null
            && self::isShopExtension($fromDigits)
            && $toDigits !== null
            && strlen($toDigits) >= 10;
    }

    private static function isDeskRingGhostLeg(?string $fromDigits, ?string $toDigits): bool
    {
        if ($toDigits === null || ! self::isShopExtension($toDigits)) {
            return false;
        }

        return $fromDigits === null || strlen($fromDigits) < 10;
    }

    private static function callerDigits(?string $normalized, ?string $raw): ?string
    {
        $raw = trim((string) $raw);

        if ($raw !== '' && self::isUnknownCallerLabel($raw)) {
            $raw = '';
        }

        $digits = PhoneNumber::normalize($normalized) ?? PhoneNumber::digits($raw !== '' ? $raw : null);

        return $digits !== null && $digits !== '' ? $digits : null;
    }

    private static function isUnknownCallerLabel(string $value): bool
    {
        return in_array(strtolower($value), ['<unknown>', 'unknown', 'anonymous'], true);
    }

    private static function isShopExtension(string $digits): bool
    {
        return in_array($digits, self::enabledExtensionNumbers(), true);
    }

    /**
     * @return list<string>
     */
    private static function enabledExtensionNumbers(): array
    {
        return Cache::remember('telephony:enabled_extension_numbers', 60, static function (): array {
            return TelephonyExtension::query()
                ->where('enabled', true)
                ->pluck('extension')
                ->map(fn (mixed $extension): string => trim((string) $extension))
                ->filter()
                ->values()
                ->all();
        });
    }
}
