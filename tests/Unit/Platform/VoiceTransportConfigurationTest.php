<?php

use App\Ark\Platform\VoiceTransportConfiguration;
use Tests\TestCase;


it('reads sip transport from deployment configuration', function (): void {
    config()->set('voice-transport.sip_registrar', 'example.sip.twilio.com');
    config()->set('voice-transport.sip_port', 5060);
    config()->set('voice-transport.sip_outbound_proxy', 'proxy.example.com');

    expect(VoiceTransportConfiguration::sipRegistrar())->toBe('example.sip.twilio.com')
        ->and(VoiceTransportConfiguration::sipPort())->toBe(5060)
        ->and(VoiceTransportConfiguration::sipOutboundProxy())->toBe('proxy.example.com');
});

it('throws when sip registrar is not configured', function (): void {
    config()->set('voice-transport.sip_registrar', '');
    config()->set('telephony.sip_provisioning.host', null);

    VoiceTransportConfiguration::sipRegistrar();
})->throws(RuntimeException::class, 'VOICE_SIP_REGISTRAR');

it('applies runtime config from an explicit registrar', function (): void {
    config()->set('voice-transport.sip_registrar', '');
    VoiceTransportConfiguration::applyRuntimeConfig('example.sip.twilio.com');

    expect(VoiceTransportConfiguration::sipRegistrar())->toBe('example.sip.twilio.com');
});
