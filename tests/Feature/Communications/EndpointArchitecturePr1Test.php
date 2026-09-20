<?php

use App\Ark\Communications\Provisioning\CommunicationDeviceMacAddress;
use App\Ark\Communications\Provisioning\CommunicationDeviceModel;
use App\Ark\Communications\Provisioning\EndpointConfigurationProjection;
use App\Ark\Communications\Provisioning\EndpointProvisionBuilder;
use App\Ark\Communications\Provisioning\EndpointProvisionFormat;
use App\Ark\Communications\Telephony\AssignExtensionToWorkstationAction;
use App\Ark\Operations\Communications\CommunicationDevice;
use App\Ark\Operations\Communications\CommunicationDeviceProvider;
use App\Ark\Operations\Communications\CommunicationDeviceStatus;
use App\Ark\Operations\Communications\ShopCommunicationsSchema;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Telephony\TelephonyExtension;
use App\Ark\Operations\Workstations\Workstation;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\CommunicationDeviceModelSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;


beforeEach(function (): void {
    ShopSettings::forgetCurrent();
    ShopSettings::current();
    $this->seed(CommunicationDeviceModelSeeder::class);
});

test('endpoint architecture pr1 tables exist', function (): void {
    expect(Schema::hasTable('communication_device_models'))->toBeTrue()
        ->and(Schema::hasTable('endpoint_configuration_projections'))->toBeTrue()
        ->and(Schema::hasColumns('communication_devices', [
            'mac_address',
            'firmware_version',
            'is_active',
            'communication_device_model_id',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('telephony_extensions', [
            'workstation_id',
            'communication_device_id',
            'secret',
        ]))->toBeTrue();
});

test('communication device model seeder registers poly vvx policies', function (): void {
    $vvx350 = CommunicationDeviceModel::query()->where('slug', 'vvx350')->first();
    $vvx450 = CommunicationDeviceModel::query()->where('slug', 'vvx450')->first();

    expect($vvx350)->not->toBeNull()
        ->and($vvx350->builder)->toBe(EndpointProvisionBuilder::Poly)
        ->and($vvx350->latest_firmware)->toBe('6.5.0')
        ->and($vvx450)->not->toBeNull()
        ->and($vvx450->builder)->toBe(EndpointProvisionBuilder::Poly);
});

test('mac address normalizes to twelve uppercase hex characters', function (): void {
    expect(CommunicationDeviceMacAddress::normalize('48:25:67:30:75:7f'))->toBe('48256730757F')
        ->and(CommunicationDeviceMacAddress::display('48256730757F'))->toBe('48:25:67:30:75:7F');
});

test('mac address is unique per shop', function (): void {
    $shop = ShopSettings::reloadCurrent();

    CommunicationDevice::query()->create([
        'shop_settings_id' => $shop->id,
        'name' => 'Front Desk',
        'mac_address' => '48256730757F',
        'provider' => CommunicationDeviceProvider::ShopPhone,
        'status' => CommunicationDeviceStatus::WaitingForRegistration,
        'is_active' => true,
        'capabilities' => ['voice'],
    ]);

    expect(fn () => CommunicationDevice::query()->create([
        'shop_settings_id' => $shop->id,
        'name' => 'Duplicate MAC',
        'mac_address' => '48256730757F',
        'provider' => CommunicationDeviceProvider::ShopPhone,
        'status' => CommunicationDeviceStatus::WaitingForRegistration,
        'is_active' => true,
        'capabilities' => ['voice'],
    ]))->toThrow(QueryException::class);
});

test('endpoint configuration projection supports current read model rows', function (): void {
    $device = CommunicationDevice::query()->create([
        'shop_settings_id' => ShopSettings::reloadCurrent()->id,
        'name' => 'Front Desk VVX350',
        'mac_address' => '48256730757F',
        'provider' => CommunicationDeviceProvider::ShopPhone,
        'status' => CommunicationDeviceStatus::WaitingForRegistration,
        'is_active' => true,
        'capabilities' => ['voice'],
    ]);

    $current = EndpointConfigurationProjection::query()->create([
        'communication_device_id' => $device->id,
        'inputs_fingerprint' => hash('sha256', 'inputs-v1'),
        'serialized_config' => '# placeholder',
        'builder' => EndpointProvisionBuilder::Poly,
        'format' => EndpointProvisionFormat::PolyCfg,
        'generated_at' => now(),
    ]);

    EndpointConfigurationProjection::query()->create([
        'communication_device_id' => $device->id,
        'inputs_fingerprint' => hash('sha256', 'inputs-v0'),
        'serialized_config' => '# superseded',
        'builder' => EndpointProvisionBuilder::Poly,
        'format' => EndpointProvisionFormat::PolyCfg,
        'generated_at' => now()->subHour(),
        'superseded_at' => now(),
    ]);

    expect($device->currentEndpointConfigurationProjection?->id)->toBe($current->id)
        ->and(EndpointConfigurationProjection::query()->current()->count())->toBe(1);
});

test('assign extension to workstation binds telephony identity to workstation not user', function (): void {
    $workstation = Workstation::query()->create([
        'shop_settings_id' => ShopSettings::reloadCurrent()->id,
        'name' => 'Front Counter',
        'is_active' => true,
    ]);

    $device = CommunicationDevice::query()->create([
        'shop_settings_id' => ShopSettings::reloadCurrent()->id,
        'workstation_id' => $workstation->id,
        'name' => 'Front Counter Left',
        'mac_address' => '48256730757F',
        'provider' => CommunicationDeviceProvider::ShopPhone,
        'status' => CommunicationDeviceStatus::WaitingForRegistration,
        'is_active' => true,
        'capabilities' => ['voice'],
    ]);

    $extension = app(AssignExtensionToWorkstationAction::class)->execute(
        workstation: $workstation,
        extension: '101',
        displayName: 'Front Counter',
        secret: 'sip-secret-101',
        communicationDevice: $device,
    );

    expect($extension->workstation_id)->toBe($workstation->id)
        ->and($extension->user_id)->toBeNull()
        ->and($extension->extension)->toBe('101')
        ->and($extension->communication_device_id)->toBe($device->id)
        ->and($extension->secret)->toBe('sip-secret-101')
        ->and($workstation->fresh()->primaryTelephonyExtension?->extension)->toBe('101');
});

test('assign extension rejects duplicate extension on another workstation', function (): void {
    $shopId = ShopSettings::reloadCurrent()->id;

    $counter = Workstation::query()->create([
        'shop_settings_id' => $shopId,
        'name' => 'Front Counter',
        'is_active' => true,
    ]);

    $parts = Workstation::query()->create([
        'shop_settings_id' => $shopId,
        'name' => 'Parts Counter',
        'is_active' => true,
    ]);

    app(AssignExtensionToWorkstationAction::class)->execute(
        workstation: $counter,
        extension: '101',
        displayName: 'Front Counter',
    );

    expect(fn () => app(AssignExtensionToWorkstationAction::class)->execute(
        workstation: $parts,
        extension: '101',
        displayName: 'Parts Counter',
    ))->toThrow(InvalidArgumentException::class);
});

test('assign extension requires device workstation match', function (): void {
    $shopId = ShopSettings::reloadCurrent()->id;

    $counter = Workstation::query()->create([
        'shop_settings_id' => $shopId,
        'name' => 'Front Counter',
        'is_active' => true,
    ]);

    $parts = Workstation::query()->create([
        'shop_settings_id' => $shopId,
        'name' => 'Parts Counter',
        'is_active' => true,
    ]);

    $device = CommunicationDevice::query()->create([
        'shop_settings_id' => $shopId,
        'workstation_id' => $counter->id,
        'name' => 'Front Desk Phone',
        'mac_address' => 'AABBCCDDEEFF',
        'provider' => CommunicationDeviceProvider::ShopPhone,
        'status' => CommunicationDeviceStatus::WaitingForRegistration,
        'is_active' => true,
        'capabilities' => ['voice'],
    ]);

    expect(fn () => app(AssignExtensionToWorkstationAction::class)->execute(
        workstation: $parts,
        extension: '102',
        displayName: 'Parts Counter',
        communicationDevice: $device,
    ))->toThrow(RuntimeException::class);
});

test('replacing hardware mac preserves workstation extension authority', function (): void {
    $shopId = ShopSettings::reloadCurrent()->id;

    $workstation = Workstation::query()->create([
        'shop_settings_id' => $shopId,
        'name' => 'Front Counter',
        'is_active' => true,
    ]);

    $oldDevice = CommunicationDevice::query()->create([
        'shop_settings_id' => $shopId,
        'workstation_id' => $workstation->id,
        'name' => 'Front Counter Left',
        'mac_address' => '48256730757F',
        'provider' => CommunicationDeviceProvider::ShopPhone,
        'status' => CommunicationDeviceStatus::Connected,
        'is_active' => false,
        'capabilities' => ['voice'],
    ]);

    app(AssignExtensionToWorkstationAction::class)->execute(
        workstation: $workstation,
        extension: '101',
        displayName: 'Front Counter',
        communicationDevice: $oldDevice,
    );

    $newDevice = CommunicationDevice::query()->create([
        'shop_settings_id' => $shopId,
        'workstation_id' => $workstation->id,
        'name' => 'Front Counter Left Replacement',
        'mac_address' => 'AABBCCDDEEFF',
        'provider' => CommunicationDeviceProvider::ShopPhone,
        'status' => CommunicationDeviceStatus::WaitingForRegistration,
        'is_active' => true,
        'capabilities' => ['voice'],
    ]);

    app(AssignExtensionToWorkstationAction::class)->execute(
        workstation: $workstation,
        extension: '101',
        displayName: 'Front Counter',
        communicationDevice: $newDevice,
    );

    $extension = TelephonyExtension::query()->where('workstation_id', $workstation->id)->first();

    expect($extension?->extension)->toBe('101')
        ->and($extension?->workstation_id)->toBe($workstation->id)
        ->and($extension?->communication_device_id)->toBe($newDevice->id)
        ->and($workstation->fresh()->name)->toBe('Front Counter');
});

test('shop assign extension route completes first contact operator path', function (): void {
    $this->seed(\Database\Seeders\ArkAuthorizationSeeder::class);

    config()->set('telephony.sip_provisioning.host', 'voice.demo-auto.test');

    $admin = \App\Models\User::factory()->create(['is_master_admin' => true])
        ->assignRole(\App\Ark\Runtime\Authorization\ArkRole::Admin->value);

    $workstation = Workstation::query()->create([
        'shop_settings_id' => ShopSettings::reloadCurrent()->id,
        'name' => 'Front Counter',
        'is_active' => true,
    ]);

    $device = CommunicationDevice::query()->create([
        'shop_settings_id' => ShopSettings::reloadCurrent()->id,
        'workstation_id' => $workstation->id,
        'name' => 'Front Desk VVX350',
        'model' => 'VVX350',
        'mac_address' => '48256730757F',
        'provider' => CommunicationDeviceProvider::ShopPhone,
        'status' => CommunicationDeviceStatus::WaitingForRegistration,
        'capabilities' => ['voice'],
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->post(route('operations.shop.workstations.extension.assign', $workstation), [
            'extension' => '101',
            'display_name' => 'Front Counter',
        ])
        ->assertRedirect(route('operations.shop.communications'))
        ->assertSessionHas('status');

    $extension = TelephonyExtension::query()->where('workstation_id', $workstation->id)->first();

    expect($extension?->extension)->toBe('101')
        ->and($extension?->display_name)->toBe('Front Counter')
        ->and($extension?->secret)->not->toBeEmpty()
        ->and($extension?->communication_device_id)->toBe($device->id)
        ->and($device->fresh()->provider_identifier)->toBe('101');

    $readiness = app(\App\Ark\Communications\Provisioning\FirstContactReadinessProjection::class)
        ->forDevice($device->fresh());

    expect($readiness['ready'])->toBeTrue()
        ->and($readiness['action_label'])->toBe('Begin First Contact')
        ->and($readiness['action_url'])->toContain('#certification');
});

test('shop communications schema is ready after pr1 migrations', function (): void {
    expect(ShopCommunicationsSchema::isReady())->toBeTrue()
        ->and(ShopCommunicationsSchema::missingRequirements())->toBe([]);
});

test('shop communications shows setup required when voice schema is missing', function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->withoutVite();

    $admin = User::factory()->create(['is_master_admin' => true])
        ->assignRole(ArkRole::Admin->value);

    Schema::drop('communication_device_models');

    $this->actingAs($admin)
        ->get(route('operations.shop.communications'))
        ->assertOk()
        ->assertSee('Database setup required')
        ->assertSee('communication_device_models table');
});
