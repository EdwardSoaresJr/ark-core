<?php

use App\Ark\Mobile\MobileStaffDevicesProjection;
use App\Ark\Mobile\RegisterMobileDeviceAction;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Telephony\TelephonyProviderType;
use App\Models\User;

test('mobile staff devices projection lists registered devices with extensions', function () {
    $user = User::factory()->create(['name' => 'Alex Rivera']);

    config([
        'voice-transport.sip_registrar' => 'voice.example.test',
        'voice-transport.sip_wss_uri' => 'wss://voice.example.test:8089/asterisk/ws',
    ]);

    ShopSettings::current()->persistTrusted([
        'telephony_provider' => TelephonyProviderType::Twilio->value,
    ]);

    app(RegisterMobileDeviceAction::class)->execute(
        $user,
        'Pixel 9',
        'android',
        '1.0.0',
        'fcm-token-test',
    );

    $rows = MobileStaffDevicesProjection::forCurrentShop()->rows();

    expect($rows)->toHaveCount(1)
        ->and($rows[0]->advisorName)->toBe('Alex Rivera')
        ->and($rows[0]->deviceName)->toBe('Pixel 9')
        ->and($rows[0]->extension)->not->toBeNull()
        ->and($rows[0]->pushTokenRegistered)->toBeTrue()
        ->and($rows[0]->voiceEnabled)->toBeTrue();
});
