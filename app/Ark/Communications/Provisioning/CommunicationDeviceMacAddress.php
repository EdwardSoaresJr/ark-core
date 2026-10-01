<?php

namespace App\Ark\Communications\Provisioning;

use InvalidArgumentException;

/**
 * Normalizes hardware MAC addresses to 12 uppercase hex characters.
 */
final class CommunicationDeviceMacAddress
{
    public static function normalize(string $value): string
    {
        $normalized = strtoupper(preg_replace('/[^0-9A-Fa-f]/', '', $value) ?? '');

        if (! self::isValid($normalized)) {
            throw new InvalidArgumentException('MAC address must be 12 hexadecimal characters.');
        }

        return $normalized;
    }

    public static function isValid(string $value): bool
    {
        return (bool) preg_match('/^[0-9A-F]{12}$/', $value);
    }

    public static function display(string $normalized): string
    {
        return implode(':', str_split(self::normalize($normalized), 2));
    }
}
