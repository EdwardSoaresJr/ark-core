<?php

use App\Ark\Mobile\RegisterMobileDeviceAction;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Telephony\TelephonyExtension;
use App\Ark\Operations\Telephony\TelephonyExtensionDeviceType;
use App\Ark\Operations\Workstations\Workstation;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);

    config()->set('voice-transport.sip_registrar', 'voice.demo-auto.test');

    ShopSettings::current()->update([
        'telephony_inbound_number' => '+17195550100',
        'telephony_provider' => \App\Ark\Operations\Telephony\TelephonyProviderType::Twilio->value,
    ]);
});

test('people lists shop names with opaque refer uri and excludes caller mobile leg', function (): void {
    $edward = User::factory()->create(['name' => 'Alex Rivera'])->assignRole(ArkRole::Advisor->value);
    $ben = User::factory()->create(['name' => 'Ben Advisor'])->assignRole(ArkRole::Advisor->value);

    $workstation = Workstation::query()->create([
        'shop_settings_id' => ShopSettings::current()->id,
        'name' => 'Front Desk',
        'is_active' => true,
    ]);

    TelephonyExtension::query()->create([
        'extension' => '101',
        'display_name' => 'Front Desk',
        'user_id' => $ben->id,
        'workstation_id' => $workstation->id,
        'device_type' => TelephonyExtensionDeviceType::DeskPhone,
        'enabled' => true,
        'secret' => 'desk-secret',
    ]);

    $token = $edward->createToken('iPhone')->plainTextToken;

    app(RegisterMobileDeviceAction::class)->execute(
        user: $edward,
        deviceName: 'iPhone',
        platform: 'ios',
        fcmToken: 'fcm-test',
    );

    $this->withToken($token)
        ->postJson('/api/mobile/telephony/voice-session')
        ->assertOk();

    $mobileExtension = TelephonyExtension::query()
        ->where('user_id', $edward->id)
        ->where('device_type', TelephonyExtensionDeviceType::MobileApp)
        ->first();

    $response = $this->withToken($token)
        ->getJson('/api/mobile/telephony/people')
        ->assertOk();

    $people = collect($response->json('people'));

    expect($people)->toHaveCount(1)
        ->and($people->first()['name'])->toBe('Ben')
        ->and($people->first()['refer_uri'])->toBe('sip:101@voice.demo-auto.test')
        ->and(collect($people)->pluck('refer_uri'))->not->toContain('sip:'.$mobileExtension->extension.'@voice.demo-auto.test');
});
