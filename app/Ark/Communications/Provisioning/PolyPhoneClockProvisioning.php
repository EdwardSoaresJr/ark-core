<?php

namespace App\Ark\Communications\Provisioning;

use App\Ark\Operations\Settings\ShopDisplayTimezone;
use DateTime;
use DateTimeZone;

/**
 * Poly VVX clock provisioning — mirrors the working floor VVX datetime profile.
 *
 * device.sntp carries city ID + current offset; tcpIpApp.sntp carries offset only.
 * daylightSavings.enable stays off; VVX350 does not reliably honor Poly city DST tables.
 */
final class PolyPhoneClockProvisioning
{
    public const DEFAULT_NTP_SERVER = 'time.google.com';

    /**
     * device.sntp.* on {@code <device>} — display clock path on VVX350.
     *
     * @return array<string, string>
     */
    public static function deviceElementAttributes(?string $shopTimezone = null): array
    {
        $timezone = self::resolveTimezone($shopTimezone);
        $offset = self::currentOffsetSeconds($timezone);
        $cityId = self::polyCityIdFor($timezone);

        $attributes = [
            'device.sntp.serverName.set' => '1',
            'device.sntp.serverName' => self::DEFAULT_NTP_SERVER,
            'device.sntp.gmtOffset.set' => '1',
            'device.sntp.gmtOffset' => (string) $offset,
        ];

        if ($cityId !== null) {
            $attributes['device.sntp.gmtOffsetcityID.set'] = '1';
            $attributes['device.sntp.gmtOffsetcityID'] = (string) $cityId;
        }

        return $attributes;
    }

    /**
     * Nested tcpIpApp.sntp — no city ID (matches working left desk phone).
     */
    public static function phoneChildren(?string $shopTimezone = null): string
    {
        $offset = self::currentOffsetSeconds(self::resolveTimezone($shopTimezone));

        $attrs = [
            'tcpIpApp.sntp.address' => self::DEFAULT_NTP_SERVER,
            'tcpIpApp.sntp.address.overrideDHCP' => '1',
            'tcpIpApp.sntp.gmtOffset' => (string) $offset,
            'tcpIpApp.sntp.gmtOffset.overrideDHCP' => '1',
            'tcpIpApp.sntp.resyncPeriod' => '3600',
            'tcpIpApp.sntp.daylightSavings.enable' => '0',
        ];

        $attrString = '';

        foreach ($attrs as $key => $value) {
            $attrString .= ' '.$key.'="'.self::escape($value).'"';
        }

        return '   <tcpIpApp>'."\n"
            .'      <tcpIpApp.sntp'.$attrString.' />'."\n"
            .'   </tcpIpApp>'."\n";
    }

    public static function polyCityIdFor(string $ianaTimezone): ?int
    {
        return self::IANA_TO_POLY_CITY_ID[$ianaTimezone] ?? null;
    }

    public static function standardOffsetSeconds(string $ianaTimezone): int
    {
        return (new DateTimeZone($ianaTimezone))
            ->getOffset(new DateTime('2026-01-15 12:00:00', new DateTimeZone('UTC')));
    }

    public static function currentOffsetSeconds(string $ianaTimezone): int
    {
        return (new DateTimeZone($ianaTimezone))
            ->getOffset(new DateTime('now', new DateTimeZone('UTC')));
    }

    private static function resolveTimezone(?string $shopTimezone): string
    {
        if (is_string($shopTimezone) && trim($shopTimezone) !== '') {
            return trim($shopTimezone);
        }

        return ShopDisplayTimezone::resolve();
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    /**
     * @var array<string, int>
     */
    private const IANA_TO_POLY_CITY_ID = [
        'Pacific/Honolulu' => 2,
        'America/Anchorage' => 3,
        'America/Los_Angeles' => 4,
        'America/Vancouver' => 4,
        'America/Tijuana' => 5,
        'America/Denver' => 6,
        'America/Boise' => 6,
        'America/Edmonton' => 6,
        'America/Chihuahua' => 7,
        'America/Mazatlan' => 8,
        'America/Phoenix' => 9,
        'America/Chicago' => 10,
        'America/Winnipeg' => 10,
        'America/Mexico_City' => 11,
        'America/Regina' => 12,
        'America/Guatemala' => 15,
        'America/New_York' => 16,
        'America/Toronto' => 16,
        'America/Detroit' => 16,
        'America/Indiana/Indianapolis' => 17,
        'America/Halifax' => 21,
        'America/St_Johns' => 26,
        'America/Sao_Paulo' => 27,
        'America/Argentina/Buenos_Aires' => 28,
        'Atlantic/Azores' => 35,
        'Europe/London' => 38,
        'Europe/Dublin' => 40,
        'Europe/Paris' => 53,
        'Europe/Berlin' => 54,
        'Europe/Rome' => 55,
        'Africa/Johannesburg' => 67,
        'Asia/Jerusalem' => 68,
        'Europe/Moscow' => 71,
        'Asia/Dubai' => 79,
        'Asia/Kolkata' => 90,
        'Asia/Tokyo' => 107,
        'Australia/Adelaide' => 109,
        'Australia/Sydney' => 113,
        'Australia/Brisbane' => 113,
        'Pacific/Auckland' => 121,
    ];
}
