<?php

namespace App\Ark\Communications\Provisioning;

/**
 * Public base URL Poly phones use as custom provisioning server (G5).
 * Phones append {MAC}.cfg — not the per-device URL shown for G4 bench tests.
 */
final class EndpointProvisionServerUrl
{
    public static function base(): string
    {
        $configured = config('telephony.sip_provisioning.base_url');

        if (is_string($configured) && filled(trim($configured))) {
            return rtrim(trim($configured), '/').'/';
        }

        return rtrim((string) config('shop.base_url'), '/').'/provision/';
    }

    public static function scheme(): string
    {
        $scheme = parse_url(self::base(), PHP_URL_SCHEME);

        return is_string($scheme) && $scheme !== '' ? strtoupper($scheme) : 'HTTPS';
    }
}
