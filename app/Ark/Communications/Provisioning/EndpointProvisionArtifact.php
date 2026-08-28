<?php

namespace App\Ark\Communications\Provisioning;

/**
 * Poly phoneprov artifact types — mirrors Asterisk res_phoneprov file naming.
 *
 * @see infra/coolify/asterisk/phoneprov/phoneprov.conf
 */
enum EndpointProvisionArtifact: string
{
    case MasterApplication = 'master_application';
    case PhoneShell = 'phone_shell';
    case WebShell = 'web_shell';
    case CloudShell = 'cloud_shell';
    case DeviceConfig = 'device_config';
    case Directory = 'directory';
    case SipConfigShell = 'sip_config_shell';
    case OptionalMissing = 'optional_missing';

    public function requiresDevice(): bool
    {
        return $this === self::DeviceConfig;
    }

    public function servesEmptyShell(): bool
    {
        return match ($this) {
            self::PhoneShell, self::WebShell, self::CloudShell, self::SipConfigShell => true,
            default => false,
        };
    }
}
