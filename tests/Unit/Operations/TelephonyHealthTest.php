<?php

use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\TelephonyEndpoint;
use App\Ark\Operations\Telephony\TelephonyEndpointType;
use App\Ark\Operations\Telephony\TelephonyHealth;

test('provider label reflects voice transport not configured in stock core', function () {
    $health = TelephonyHealth::forCurrentShop();

    expect($health->providerLabel())->toBe('Voice transport not configured')
        ->and($health->providerTone('success'))->toBe('muted');
});

test('connection tone is danger when messaging transport is not configured', function () {
    $health = TelephonyHealth::forCurrentShop();

    expect($health->connectionTone())->toBe('danger')
        ->and($health->connectionLabel())->toBe('Not connected');
});

test('connection tone is warning when shop number is saved but no ring targets exist', function () {
    bindFakeOutboundSms();

    ShopSettings::current()->update([
        'telephony_inbound_number' => '+17195550100',
    ]);

    $health = TelephonyHealth::forCurrentShop();

    expect($health->connectionTone())->toBe('warning')
        ->and($health->connectionLabel())->toBe('Needs ring endpoint');
});

test('connection tone is success when transport endpoints and shop number are ready', function () {
    bindFakeOutboundSms();
    config()->set('broadcasting.default', 'reverb');
    config()->set('broadcasting.connections.reverb.key', 'reverb-key');

    ShopSettings::current()->update([
        'telephony_inbound_number' => '+17195550100',
    ]);

    TelephonyEndpoint::query()->create([
        'name' => 'Advisor Cell',
        'type' => TelephonyEndpointType::Cell,
        'destination' => '+17195550999',
        'enabled' => true,
        'position' => 0,
    ]);

    cache()->put(TelephonyHealth::WEBHOOK_RECEIVED_CACHE_KEY, now(), now()->addHour());

    $health = TelephonyHealth::forCurrentShop();

    expect($health->connectionTone())->toBe('success')
        ->and($health->connectionLabel())->toBe('Connected')
        ->and($health->webhookTone())->toBe('success');
});

test('webhook tone is warning when no voice signal has arrived yet', function () {
    bindFakeOutboundSms();

    ShopSettings::current()->update([
        'telephony_inbound_number' => '+17195550100',
    ]);

    TelephonyEndpoint::query()->create([
        'name' => 'Advisor Cell',
        'type' => TelephonyEndpointType::Cell,
        'destination' => '+17195550999',
        'enabled' => true,
        'position' => 0,
    ]);

    cache()->forget(TelephonyHealth::WEBHOOK_RECEIVED_CACHE_KEY);
    CallSession::query()->delete();

    $health = TelephonyHealth::forCurrentShop();

    expect($health->webhookTone())->toBe('warning')
        ->and($health->webhookLabel())->toBe('Waiting for first call');
});
