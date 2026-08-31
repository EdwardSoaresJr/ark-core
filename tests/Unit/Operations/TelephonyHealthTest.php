<?php

use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\TelephonyEndpoint;
use App\Ark\Operations\Telephony\TelephonyEndpointType;
use App\Ark\Operations\Telephony\TelephonyHealth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(RefreshDatabase::class);

test('provider tone is success when telephony signals are healthy', function () {
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

    expect($health->providerTone('success'))->toBe('success')
        ->and($health->providerLabel())->toBe('Twilio · Enabled');
});

test('provider tone is danger when twilio credentials are missing', function () {
        
    $health = TelephonyHealth::forCurrentShop();

    expect($health->providerTone('success'))->toBe('danger')
        ->and($health->providerLabel())->toBe('Twilio');
});

test('provider tone is warning when any operational signal is degraded', function () {
        
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

    expect($health->providerTone('warning'))->toBe('warning');
});
