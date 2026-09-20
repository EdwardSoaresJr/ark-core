<?php

use App\Ark\Operations\Communications\CommunicationDevice;
use App\Ark\Operations\Communications\CommunicationDeviceProvider;
use App\Ark\Operations\Communications\CommunicationDeviceStatus;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Telephony\TelephonyExtension;
use App\Ark\Operations\Workstations\Workstation;
use App\Ark\Platform\VoiceTransportConfiguration;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\CommunicationDeviceModelSeeder;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(CommunicationDeviceModelSeeder::class);
    ShopSettings::forgetCurrent();
    ShopSettings::current();

    foreach (['VOICE_SIP_REGISTRAR', 'ASTERISK_PROVISIONING_HOST', 'ARK_ASTERISK_ENV_PATH', 'ARK_SHARED_SECRETS_PATH'] as $key) {
        putenv($key);
        unset($_ENV[$key], $_SERVER[$key]);
    }

    File::delete(VoiceTransportConfiguration::storagePath());
    File::delete(storage_path('framework/testing/ark-production.env'));

    config()->set('telephony.sip_provisioning.host', 'voice.demo-auto.test');
    config()->set('voice-transport.sip_registrar', 'voice.demo-auto.test');
    config()->set('telephony.sip_provisioning.default_password', 'secret-101');
});

test('pending device appears under needs attention when shop is operating', function (): void {
    $this->withoutVite();

    $admin = User::factory()->create(['is_master_admin' => true])->assignRole(ArkRole::Admin->value);

    $station = Workstation::query()->create([
        'shop_settings_id' => ShopSettings::reloadCurrent()->id,
        'name' => 'Front Counter',
        'is_active' => true,
    ]);

    CommunicationDevice::query()->create([
        'shop_settings_id' => ShopSettings::reloadCurrent()->id,
        'workstation_id' => $station->id,
        'name' => 'Front Counter · VVX450',
        'model' => 'VVX450',
        'mac_address' => '112233445566',
        'provider' => CommunicationDeviceProvider::ShopPhone,
        'status' => CommunicationDeviceStatus::Connected,
        'is_active' => true,
        'capabilities' => ['voice'],
    ]);

    CommunicationDevice::query()->create([
        'shop_settings_id' => ShopSettings::reloadCurrent()->id,
        'name' => 'Poly VVX450',
        'model' => 'VVX450',
        'mac_address' => 'AABBCCDDEEFF',
        'provider' => CommunicationDeviceProvider::ShopPhone,
        'status' => CommunicationDeviceStatus::Discovered,
        'is_active' => true,
        'capabilities' => ['voice'],
    ]);

    ShopSettings::current()->persistTrusted([
        'telephony_inbound_number' => '7194136227',
    ]);

    $this->actingAs($admin)
        ->get(route('operations.shop.communications'))
        ->assertOk()
        ->assertSee('Needs attention', false)
        ->assertSee('A new phone is ready to use.', false)
        ->assertSee('Where should it be used?', false)
        ->assertSee('Front Counter', false)
        ->assertDontSee('Activate station', false);
});

test('assigning pending device to station provisions without manual mac entry', function (): void {
    $this->withoutVite();

    $admin = User::factory()->create(['is_master_admin' => true])->assignRole(ArkRole::Admin->value);

    $station = Workstation::query()->create([
        'shop_settings_id' => ShopSettings::reloadCurrent()->id,
        'name' => 'Front Counter',
        'is_active' => true,
    ]);

    $device = CommunicationDevice::query()->create([
        'shop_settings_id' => ShopSettings::reloadCurrent()->id,
        'name' => 'Poly VVX350',
        'model' => 'VVX350',
        'mac_address' => 'AABBCCDDEEFF',
        'provider' => CommunicationDeviceProvider::ShopPhone,
        'status' => CommunicationDeviceStatus::Discovered,
        'is_active' => true,
        'capabilities' => ['voice'],
    ]);

    $this->actingAs($admin)
        ->post(route('operations.shop.devices.assign-station', $device), [
            'workstation_id' => $station->id,
        ])
        ->assertRedirect(route('operations.shop.devices.show', $device));

    $device->refresh();

    expect($device->workstation_id)->toBe($station->id)
        ->and($device->status)->toBe(CommunicationDeviceStatus::WaitingForRegistration)
        ->and(TelephonyExtension::primaryForWorkstation($station->id))->not->toBeNull();

    $this->actingAs($admin)
        ->get(route('operations.shop.communications'))
        ->assertOk()
        ->assertSee('Waiting for registration', false)
        ->assertSee('Front Counter', false)
        ->assertDontSee('A new phone is ready to use.', false);
});
