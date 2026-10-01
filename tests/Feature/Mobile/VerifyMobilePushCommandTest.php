<?php

use App\Ark\Operations\Settings\ShopSettings;

test('mobile push verify fails when firebase credentials path uses host mount inside container', function () {
    config([
        'mobile.push.credentials_path' => '/data/ark-shared/storage/app/private/firebase-mobile-service-account.json',
    ]);

    ShopSettings::current()->persistTrusted([
        'mobile_push' => ['enabled' => true],
    ]);

    $this->artisan('ark:mobile-push:verify')
        ->assertFailed()
        ->expectsOutputToContain('host path');
});

test('mobile push verify passes with container path and readable credentials file', function () {
    $path = tempnam(sys_get_temp_dir(), 'ark-fcm-verify-');

    file_put_contents($path, json_encode([
        'type' => 'service_account',
        'project_id' => 'demo-auto-ark-mobile',
        'client_email' => 'fcm@test.iam.gserviceaccount.com',
        'private_key' => "-----BEGIN PRIVATE KEY-----\ntest\n-----END PRIVATE KEY-----\n",
    ], JSON_THROW_ON_ERROR));

    config(['mobile.push.credentials_path' => $path]);

    ShopSettings::current()->persistTrusted([
        'mobile_push' => ['enabled' => true],
    ]);

    $this->artisan('ark:mobile-push:verify')
        ->assertSuccessful()
        ->expectsOutputToContain('Mobile push transport is operational.');

    @unlink($path);
});
