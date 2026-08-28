<?php

use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Telephony\TelephonyEndpoint;
use App\Ark\Operations\Telephony\TelephonyEndpointType;
use App\Ark\Operations\Telephony\TelephonyProgrammableVoiceGuard;
use App\Ark\Operations\Telephony\TelephonyProviderType;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    config()->set('services.twilio.auth_token', null);
});

test('programmable voice guard is always active after twilio native reset', function (): void {
    ShopSettings::current()->update([
        'telephony_provider' => 'asterisk',
    ]);

    expect(TelephonyProgrammableVoiceGuard::isActive())->toBeTrue();
});

test('legacy sip outbound twiml omits recording disclaimer when outbound recording is disabled', function (): void {
    ShopSettings::current()->update([
        'telephony_provider' => TelephonyProviderType::Twilio->value,
        'telephony_call_flow' => array_merge(
            ShopSettings::defaultTelephonyCallFlow(),
            ['record_outbound_calls' => false],
        ),
    ]);

    $endpoint = TelephonyEndpoint::query()->create([
        'name' => 'Legacy Desk',
        'type' => TelephonyEndpointType::Sip,
        'destination' => 'sip:101@lugsnplugs.sip.twilio.com',
        'enabled' => true,
        'position' => 0,
    ]);

    $provider = app(\App\Ark\Operations\Telephony\Providers\TwilioTelephonyProvider::class);
    $payload = $provider->parseSipOutboundVoiceRequest(new \Illuminate\Http\Request([
        'From' => $endpoint->destination,
        'To' => 'sip:+17195551234@lugsnplugs.sip.twilio.com',
        'CallSid' => 'CAlegacyout',
    ]));

    $twiml = $provider->buildSipOutboundVoiceResponse($payload, $endpoint, '+17194136227');

    expect($twiml)
        ->not->toContain('This call may be recorded')
        ->not->toContain('record="record-from-answer"')
        ->toContain('<Dial');
});
