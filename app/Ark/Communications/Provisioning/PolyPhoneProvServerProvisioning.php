<?php

namespace App\Ark\Communications\Provisioning;

/**
 * Poly device.prov.* — pins phones to ARK /provision/ instead of legacy DHCP/Asterisk hosts.
 */
final class PolyPhoneProvServerProvisioning
{
    public static function phoneDeviceElement(?string $shopTimezone = null): string
    {
        $attributes = [
            'device.set' => '1',
            'device.prov.serverName.set' => '1',
            'device.prov.serverName' => self::serverName(),
            'device.prov.serverType.set' => '1',
            'device.prov.serverType' => self::serverType(),
            'device.prov.tagSerialNo.set' => '1',
            'device.prov.tagSerialNo' => '1',
            ...PolyPhoneClockProvisioning::deviceElementAttributes($shopTimezone),
        ];

        $parts = [];

        foreach ($attributes as $key => $value) {
            $parts[] = $key.'="'.self::escape($value).'"';
        }

        return '   <device '.implode(' ', $parts).' />'."\n";
    }

    private static function serverName(): string
    {
        $base = EndpointProvisionServerUrl::base();
        $host = parse_url($base, PHP_URL_HOST);
        $path = parse_url($base, PHP_URL_PATH) ?? '/provision/';

        if (! is_string($host) || $host === '') {
            throw new \RuntimeException('Provisioning server URL is not configured.');
        }

        $path = '/'.trim($path, '/').'/';

        return $host.$path;
    }

    private static function serverType(): string
    {
        return match (EndpointProvisionServerUrl::scheme()) {
            'HTTPS' => 'HTTPS',
            'HTTP' => 'HTTP',
            default => 'HTTPS',
        };
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }
}
