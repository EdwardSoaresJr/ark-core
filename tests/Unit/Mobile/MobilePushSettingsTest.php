<?php

use App\Ark\Mobile\Push\MobilePushSettings;
use App\Ark\Operations\Settings\ShopSettings;
use Tests\TestCase;


test('mobile push resolves firebase project id from server credentials file', function () {
    $path = tempnam(sys_get_temp_dir(), 'ark-fcm-');

    file_put_contents($path, json_encode([
        'type' => 'service_account',
        'project_id' => 'demo-auto-ark-mobile',
        'client_email' => 'fcm@test.iam.gserviceaccount.com',
        'private_key' => "-----BEGIN PRIVATE KEY-----\ntest\n-----END PRIVATE KEY-----\n",
    ], JSON_THROW_ON_ERROR));

    config([
        'mobile.push.credentials_path' => $path,
        'mobile.push.enabled' => true,
    ]);

    ShopSettings::current()->persistTrusted([
        'mobile_push' => ['enabled' => true],
    ]);

    $settings = MobilePushSettings::current();

    expect($settings->resolvedProjectId())->toBe('demo-auto-ark-mobile')
        ->and($settings->isOperational())->toBeTrue()
        ->and($settings->credentialsSourceLabel())->toBe('Platform server file');

    @unlink($path);
});

test('mobile push is not operational when shop dispatch is disabled', function () {
    $path = tempnam(sys_get_temp_dir(), 'ark-fcm-');

    file_put_contents($path, json_encode([
        'type' => 'service_account',
        'project_id' => 'demo-auto-ark-mobile',
        'client_email' => 'fcm@test.iam.gserviceaccount.com',
        'private_key' => "-----BEGIN PRIVATE KEY-----\ntest\n-----END PRIVATE KEY-----\n",
    ], JSON_THROW_ON_ERROR));

    config(['mobile.push.credentials_path' => $path]);

    ShopSettings::current()->persistTrusted([
        'mobile_push' => ['enabled' => false],
    ]);

    expect(MobilePushSettings::current()->isOperational())->toBeFalse();

    @unlink($path);
});
