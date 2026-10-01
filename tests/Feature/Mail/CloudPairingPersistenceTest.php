<?php

use App\Ark\Platform\PlatformConnection;
use App\Ark\Mail\ArkMailActivationClient;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

beforeEach(function () {
    config([
        'services.ark_cloud.base_url' => 'http://ark-cloud.test',
        'services.ark_mail.base_url' => 'http://ark-cloud.test',
    ]);
});

it('persists pairing state outside the session', function () {
    $pairingPublicId = (string) Str::uuid();

    Http::fake([
        '*/api/v1/pairing/start' => Http::response([
            'ok' => true,
            'pairing_code' => 'ABCD1234',
            'public_id' => $pairingPublicId,
            'expires_at' => now()->addMinutes(10)->toIso8601String(),
        ], 200),
    ]);

    $started = app(ArkMailActivationClient::class)->activate();

    expect($started['pairing_code'])->toBe('ABCD1234')
        ->and($started['pairing_public_id'])->toBe($pairingPublicId);

    // Simulate a new request / process: no session flash required.
    $settings = ShopSettings::current()->fresh();
    $cloud = new PlatformConnection($settings);

    expect($cloud->isPairing())->toBeTrue()
        ->and($cloud->pairingPublicId())->toBe($pairingPublicId)
        ->and($cloud->pairingCode())->toBe('ABCD1234')
        ->and($cloud->credential())->toBeNull()
        ->and($settings->ark_mail_credential)->toBeNull()
        ->and($settings->cloud_credential)->toBeNull();
});

it('stores the cloud credential on the cloud connection after claim', function () {
    $pairingPublicId = (string) Str::uuid();

    ShopSettings::current()->persistTrusted([
        'cloud_status' => 'pairing',
        'cloud_base_url' => 'http://ark-cloud.test',
        'cloud_pairing_public_id' => $pairingPublicId,
        'cloud_pairing_code' => 'ABCD1234',
        'cloud_pairing_expires_at' => now()->addMinutes(10),
        'ark_mail_status' => 'pairing',
    ]);

    Http::fake([
        '*/api/v1/pairing/claim' => Http::response([
            'ok' => true,
            'credential' => 'arkcloud_test_secret_value',
            'shop_public_id' => (string) Str::uuid(),
            'status' => 'connected',
        ], 200),
    ]);

    app(ArkMailActivationClient::class)->claimPairing();

    $settings = ShopSettings::current()->fresh();
    $cloud = new PlatformConnection($settings);

    expect($cloud->isConnected())->toBeTrue()
        ->and($cloud->credential())->toBe('arkcloud_test_secret_value')
        ->and($settings->cloud_credential)->toBe('arkcloud_test_secret_value')
        ->and($settings->ark_mail_credential)->toBeNull()
        ->and($cloud->pairingPublicId())->toBeNull()
        ->and($cloud->pairingCode())->toBeNull();
});

it('clears expired pairing state', function () {
    ShopSettings::current()->persistTrusted([
        'cloud_status' => 'pairing',
        'cloud_pairing_public_id' => (string) Str::uuid(),
        'cloud_pairing_code' => 'ZZZZ9999',
        'cloud_pairing_expires_at' => now()->subMinute(),
        'ark_mail_status' => 'pairing',
    ]);

    $cloud = PlatformConnection::current();
    expect($cloud->isPairing())->toBeFalse();

    $settings = ShopSettings::current()->fresh();
    expect($settings->cloud_status)->toBeNull()
        ->and($settings->cloud_pairing_public_id)->toBeNull()
        ->and($settings->cloud_pairing_code)->toBeNull();
});
