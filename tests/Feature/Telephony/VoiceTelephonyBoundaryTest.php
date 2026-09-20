<?php

use App\Ark\Operations\Telephony\Contracts\TelephonyProvider;
use App\Ark\Operations\Telephony\IncomingCallPayload;
use App\Ark\Operations\Telephony\Providers\NotConfiguredTelephonyProvider;
use App\Ark\Operations\Telephony\TelephonyProviderManager;
use App\Ark\Operations\Telephony\TelephonyProviderType;
use Illuminate\Http\Request;

test('stock core resolves not-configured telephony provider', function () {
    $manager = app(TelephonyProviderManager::class);
    $provider = $manager->current();

    expect($provider)->toBeInstanceOf(NotConfiguredTelephonyProvider::class)
        ->and($provider->type())->toBe(TelephonyProviderType::None)
        ->and($manager->currentType())->toBe(TelephonyProviderType::None)
        ->and(app(TelephonyProvider::class))->toBeInstanceOf(NotConfiguredTelephonyProvider::class);
});

test('not-configured telephony provider throws on voice parse and response', function () {
    $provider = app(NotConfiguredTelephonyProvider::class);

    expect(fn () => $provider->parseIncomingVoiceRequest(Request::create('/', 'POST')))
        ->toThrow(RuntimeException::class, 'Voice telephony is not configured.');

    $payload = new IncomingCallPayload(
        provider: TelephonyProviderType::None,
        providerCallSid: 'CA-test',
        fromNumber: '+17195550100',
        toNumber: '+17195550199',
        normalizedFrom: '7195550100',
        normalizedTo: '7195550199',
        status: \App\Ark\Operations\Telephony\CallSessionStatus::Ringing,
        rawPayload: [],
    );

    expect(fn () => $provider->buildIncomingVoiceResponse($payload))
        ->toThrow(RuntimeException::class, 'Voice telephony is not configured.');
});

test('telephony provider manager twilio helper throws', function () {
    expect(fn () => app(TelephonyProviderManager::class)->twilio())
        ->toThrow(RuntimeException::class, 'Voice telephony is not configured.');
});
