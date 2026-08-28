<?php

namespace App\Ark\Communications\Provisioning\Builders;

use App\Ark\Communications\Provisioning\EndpointProvisionContext;
use App\Ark\Communications\Provisioning\PolyPhoneClockProvisioning;
use App\Ark\Communications\Provisioning\PolyPhoneNatProvisioning;
use App\Ark\Communications\Provisioning\PolyPhoneProvServerProvisioning;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Platform\VoiceTransportConfiguration;
use RuntimeException;

/**
 * Poly config/{MAC} body — port of Asterisk phoneprov polycom.xml + polycom_line.xml.
 */
final class PolyPhoneProvDeviceConfigBuilder
{
    public function build(EndpointProvisionContext $context): string
    {
        $device = $context->device;
        $extension = $context->extension;
        $identity = trim($extension->extension);

        if ($identity === '') {
            throw new RuntimeException('Workstation extension is required for provisioning.');
        }

        $server = VoiceTransportConfiguration::sipRegistrar();
        $port = VoiceTransportConfiguration::sipPort();
        $password = $this->resolveCredential($extension, $identity);
        $displayName = trim($extension->display_name) !== ''
            ? trim($extension->display_name)
            : $device->name;
        $label = trim($extension->display_name) !== '' ? trim($extension->display_name) : $identity;

        $registration = $this->registrationAttributes(
            line: 1,
            identity: $identity,
            label: $label,
            displayName: $displayName,
            password: $password,
            server: $server,
            port: $port,
        );

        $clockChildren = PolyPhoneClockProvisioning::phoneChildren(
            ShopDisplayTimezone::resolve(),
        );

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n"
            .'<phone1>'."\n"
            .PolyPhoneProvServerProvisioning::phoneDeviceElement(ShopDisplayTimezone::resolve())
            .PolyPhoneNatProvisioning::phoneChildren()
            .'   <reg '.$registration.' />'."\n"
            .$clockChildren
            .'   <call>'."\n"
            .'      <donotdisturb call.donotdisturb.perReg="1" />'."\n"
            .'      <missedCallTracking call.missedCallTracking.1.enabled="0" />'."\n"
            .'   </call>'."\n"
            .'   <msg msg.bypassInstantMessage="1" />'."\n"
            .'   <HTTPD httpd.enabled="1" />'."\n"
            .'</phone1>'."\n";
    }

    private function registrationAttributes(
        int $line,
        string $identity,
        string $label,
        string $displayName,
        string $password,
        string $server,
        int $port,
    ): string {
        $attributes = [
            "reg.{$line}.displayName" => $displayName,
            "reg.{$line}.address" => $identity,
            "reg.{$line}.label" => $label,
            "reg.{$line}.type" => 'private',
            "reg.{$line}.auth.userId" => $identity,
            "reg.{$line}.auth.password" => $password,
            "reg.{$line}.server.1.address" => $server,
            "reg.{$line}.server.1.port" => (string) $port,
            "reg.{$line}.server.1.outboundProxy" => $server,
            "reg.{$line}.server.1.transport" => 'UDPOnly',
            "reg.{$line}.server.1.expires" => '120',
            "reg.{$line}.server.1.retryTimeOut" => '30',
            "reg.{$line}.server.1.register" => '1',
            "reg.{$line}.lineKeys" => '1',
        ];

        $parts = [];

        foreach ($attributes as $key => $value) {
            $parts[] = $key.'="'.$this->escape($value).'"';
        }

        return implode(' ', $parts);
    }

    private function resolveCredential(\App\Ark\Operations\Telephony\TelephonyExtension $extension, string $identity): string
    {
        if (filled($extension->secret)) {
            return (string) $extension->secret;
        }

        $configured = config('telephony.sip_provisioning.passwords.'.$identity);

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        $default = config('telephony.sip_provisioning.default_password');

        if (is_string($default) && $default !== '') {
            return $default;
        }

        throw new RuntimeException('No provisioning credential configured for this extension.');
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }
}
