<?php

namespace App\Ark\Communications\Provisioning;

/**
 * Poly NAT settings for Twilio SIP registration behind a shop LAN.
 *
 * Without contact rewrite, Twilio stores the phone private IP (e.g. 192.168.1.131)
 * and inbound <Dial><Sip> legs fail with error 32011.
 *
 * @see https://www.twilio.com/docs/voice/api/sip-registration
 */
final class PolyPhoneNatProvisioning
{
    /** Shop WAN IP — Twilio must not receive a private LAN contact (error 32011). */
    public const DEFAULT_PUBLIC_IP = '71.196.200.50';

    public static function phoneChildren(?string $publicIp = null): string
    {
        $publicIp = trim((string) ($publicIp ?? config('voice-transport.sip_public_ip')));

        if ($publicIp === '') {
            $publicIp = self::DEFAULT_PUBLIC_IP;
        }

        $attributes = [
            'nat.ip' => $publicIp,
            'nat.keepalive.interval' => '20',
        ];

        $attrString = '';

        foreach ($attributes as $key => $value) {
            if ($value === '') {
                continue;
            }

            $attrString .= ' '.$key.'="'.htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8').'"';
        }

        return '   <nat'.$attrString.' />'."\n";
    }
}
