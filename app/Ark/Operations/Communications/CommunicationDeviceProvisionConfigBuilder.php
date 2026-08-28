<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Operations\Telephony\TelephonyExtension;
use App\Ark\Platform\VoiceTransportConfiguration;
use RuntimeException;

/**
 * Builds installer-facing device config. SIP details stay in the file — never operator UI.
 *
 * HTTP URLs from shop identity; SIP from deployment transport configuration.
 */
final class CommunicationDeviceProvisionConfigBuilder
{
    public function resolveIdentity(CommunicationDevice $device): string
    {
        return $this->resolveDeviceIdentity($device);
    }

    public function build(CommunicationDevice $device): string
    {
        $identity = $this->resolveDeviceIdentity($device);
        $registrar = VoiceTransportConfiguration::sipRegistrar();
        $port = VoiceTransportConfiguration::sipPort();
        $outboundProxy = VoiceTransportConfiguration::sipOutboundProxy();
        $password = $this->resolveCredential($identity);

        $lines = [
            '# ARK CommunicationDevice provisioning',
            '# Device: '.$device->name,
            '# Identity: '.$identity,
            '',
            'device.sett.serverFactory="Custom"',
            'device.sett.serverType="String"',
            'device.sett.serverValue="'.$this->escape($registrar).'"',
            '',
            'reg.1.server.1.address="'.$this->escape($registrar).'"',
            'reg.1.server.1.port="'.$port.'"',
            'reg.1.server.1.transport="UDPOnly"',
            'reg.1.auth.userId="'.$identity.'"',
            'reg.1.auth.password="'.$password.'"',
            'reg.1.displayName="'.$this->escape($device->name).'"',
        ];

        if ($outboundProxy !== null) {
            $lines[] = 'reg.1.server.1.outboundProxy="'.$this->escape($outboundProxy).'"';
        }

        return implode("\n", $lines)."\n";
    }

    private function resolveDeviceIdentity(CommunicationDevice $device): string
    {
        if (filled($device->provider_identifier)) {
            return trim((string) $device->provider_identifier);
        }

        if ($device->assigned_user_id !== null) {
            $extension = TelephonyExtension::query()
                ->where('user_id', $device->assigned_user_id)
                ->orderBy('extension')
                ->value('extension');

            if (filled($extension)) {
                return trim((string) $extension);
            }
        }

        $used = CommunicationDevice::query()
            ->whereNotNull('provider_identifier')
            ->pluck('provider_identifier')
            ->map(fn (mixed $value): string => trim((string) $value))
            ->filter()
            ->all();

        for ($candidate = 101; $candidate <= 199; $candidate++) {
            $number = (string) $candidate;

            if (! in_array($number, $used, true)) {
                return $number;
            }
        }

        throw new RuntimeException('No available device identity.');
    }

    private function resolveCredential(string $identity): string
    {
        $configured = config('telephony.sip_provisioning.passwords.'.$identity);

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        $default = config('telephony.sip_provisioning.default_password');

        if (is_string($default) && $default !== '') {
            return $default;
        }

        throw new RuntimeException('No provisioning credential configured for this device identity.');
    }

    private function escape(string $value): string
    {
        return str_replace('"', '\\"', $value);
    }
}
