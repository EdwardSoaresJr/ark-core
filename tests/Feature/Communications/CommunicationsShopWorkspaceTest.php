<?php

use App\Ark\Communications\Provisioning\FirstContactReadinessProjection;
use App\Ark\Communications\Provisioning\RegenerateEndpointConfigurationAction;
use App\Ark\Communications\Telephony\AssignExtensionToWorkstationAction;
use App\Ark\Operations\Communications\CommunicationDevice;
use App\Ark\Operations\Communications\CommunicationDeviceProvider;
use App\Ark\Operations\Communications\CommunicationDeviceStatus;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionDirection;
use App\Ark\Operations\Telephony\CallSessionStatus;
use App\Ark\Operations\Telephony\TelephonyEndpoint;
use App\Ark\Operations\Telephony\TelephonyEndpointType;
use App\Ark\Operations\Telephony\TelephonyExtension;
use App\Ark\Operations\Telephony\TelephonyExtensionDeviceType;
use App\Ark\Operations\Workstations\Workstation;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\CommunicationDeviceModelSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    ShopSettings::forgetCurrent();
    ShopSettings::current();
});

test('shop communications workspace is restricted to settings managers', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $this->actingAs($advisor)
        ->get(route('operations.shop.communications'))
        ->assertForbidden();
});

test('shop communications answers whether the shop can communicate', function (): void {
    $this->withoutVite();

    $admin = User::factory()->create(['name' => 'Edward Soares', 'is_master_admin' => true])->assignRole(ArkRole::Admin->value);

    ShopSettings::current()->persistTrusted([
        'telephony_inbound_number' => '7194136227',
    ]);

    $molly = User::factory()->create(['name' => 'Molly Advisor'])->assignRole(ArkRole::Advisor->value);

    CommunicationDevice::query()->create([
        'shop_settings_id' => ShopSettings::reloadCurrent()->id,
        'name' => 'Front Desk VVX450',
        'assigned_user_id' => $admin->id,
        'provider' => CommunicationDeviceProvider::ShopPhone,
        'status' => CommunicationDeviceStatus::Connected,
        'capabilities' => ['voice', 'transfer', 'hold'],
    ]);

    CommunicationDevice::query()->create([
        'shop_settings_id' => ShopSettings::reloadCurrent()->id,
        'name' => 'Advisor Desk',
        'assigned_user_id' => $molly->id,
        'provider' => CommunicationDeviceProvider::ShopPhone,
        'status' => CommunicationDeviceStatus::Connected,
        'capabilities' => ['voice'],
    ]);

    TelephonyEndpoint::query()->create([
        'name' => 'Edward cell',
        'type' => TelephonyEndpointType::Cell,
        'destination' => '7195550100',
        'user_id' => $admin->id,
        'enabled' => true,
        'position' => 1,
    ]);

    $this->actingAs($admin)
        ->get(route('operations.shop.communications'))
        ->assertOk()
        ->assertSee('Stations &amp; Phones', false)
        ->assertSee('Coverage today', false)
        ->assertSee('Edward Soares')
        ->assertSee('Communications Healthy', false)
        ->assertDontSee('Asterisk')
        ->assertDontSee('SIP')
        ->assertDontSee('Current Activity');
});

test('shop communications projects live calls in todays coverage', function (): void {
    $admin = User::factory()->create(['name' => 'Edward Soares'])->assignRole(ArkRole::Admin->value);
    $molly = User::factory()->create(['name' => 'Molly Advisor'])->assignRole(ArkRole::Advisor->value);

    CommunicationDevice::query()->create([
        'shop_settings_id' => ShopSettings::reloadCurrent()->id,
        'name' => 'Front Desk VVX450',
        'assigned_user_id' => $admin->id,
        'provider' => CommunicationDeviceProvider::ShopPhone,
        'status' => CommunicationDeviceStatus::Connected,
        'capabilities' => ['voice'],
    ]);

    CommunicationDevice::query()->create([
        'shop_settings_id' => ShopSettings::reloadCurrent()->id,
        'name' => 'Advisor Desk',
        'assigned_user_id' => $molly->id,
        'provider' => CommunicationDeviceProvider::ShopPhone,
        'status' => CommunicationDeviceStatus::Connected,
        'capabilities' => ['voice'],
    ]);

    $customer = Customer::query()->create([
        'first_name' => 'John',
        'last_name' => 'Smith',
        'phone' => '7195551212',
    ]);

    CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAactivity001',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195551212',
        'to_number' => '+17194136227',
        'normalized_from' => '7195551212',
        'status' => CallSessionStatus::Answered,
        'customer_id' => $customer->id,
        'owned_by_user_id' => $admin->id,
        'started_at' => now()->subMinute(),
        'answered_at' => now()->subMinute(),
    ]);

    $this->actingAs($admin)
        ->get(route('operations.shop.communications'))
        ->assertOk()
        ->assertSee('Coverage today', false)
        ->assertSee('On a call with John Smith')
        ->assertSee('Available')
        ->assertDontSee('Current Activity');
});

test('shop communications shows setup in progress when workstation lacks phone path', function (): void {
    $this->withoutVite();

    $admin = User::factory()->create(['is_master_admin' => true])->assignRole(ArkRole::Admin->value);

    Workstation::query()->create([
        'shop_settings_id' => ShopSettings::reloadCurrent()->id,
        'name' => 'Front Counter',
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->get(route('operations.shop.communications'))
        ->assertOk()
        ->assertSee('Setup in progress')
        ->assertSee('Plug in a phone')
        ->assertDontSee('Assign extension')
        ->assertDontSee('SIP secret')
        ->assertDontSee('Floor Coverage');
});

test('creating workstation auto-assigns extension and advances to phone step', function (): void {
    $this->withoutVite();

    $admin = User::factory()->create(['is_master_admin' => true])->assignRole(ArkRole::Admin->value);

    $this->actingAs($admin)
        ->post(route('operations.shop.workstations.store'), [
            'name' => 'Right',
            'location_label' => 'Front desk',
        ])
        ->assertRedirect(route('operations.shop.communications'))
        ->assertSessionHas('status');

    $workstation = Workstation::query()->where('name', 'Right')->firstOrFail();

    expect(TelephonyExtension::primaryForWorkstation($workstation->id)?->extension)->toBe('101')
        ->and(TelephonyExtension::primaryForWorkstation($workstation->id)?->display_name)->toBe('Right')
        ->and(TelephonyExtension::primaryForWorkstation($workstation->id)?->secret)->not->toBeEmpty();

    $this->actingAs($admin)
        ->get(route('operations.shop.communications'))
        ->assertOk()
        ->assertSee('Plug in a phone')
        ->assertSee('Waiting for a phone')
        ->assertDontSee('Assign extension')
        ->assertDontSee('SIP secret')
        ->assertDontSee('No extension');

    $this->actingAs($admin)
        ->get(route('operations.shop.communications'))
        ->assertOk()
        ->assertSee('Plug in a phone')
        ->assertSee('Waiting for a phone')
        ->assertDontSee('Assign extension');

    $device = CommunicationDevice::query()->create([
        'shop_settings_id' => ShopSettings::reloadCurrent()->id,
        'workstation_id' => $workstation->id,
        'name' => 'Right VVX350',
        'model' => 'VVX350',
        'mac_address' => '48256730757F',
        'provider' => CommunicationDeviceProvider::ShopPhone,
        'status' => CommunicationDeviceStatus::WaitingForRegistration,
        'capabilities' => ['voice'],
        'is_active' => true,
    ]);

    $readiness = app(FirstContactReadinessProjection::class)->forDevice($device->fresh());

    expect($readiness['checks'][3])->toMatchArray(['label' => 'Extension assigned', 'passed' => true]);
});

test('adding phone to legacy workstation without extension assigns extension automatically', function (): void {
    $this->withoutVite();

    $admin = User::factory()->create(['is_master_admin' => true])->assignRole(ArkRole::Admin->value);

    $workstation = Workstation::query()->create([
        'shop_settings_id' => ShopSettings::reloadCurrent()->id,
        'name' => 'Right',
        'is_active' => true,
    ]);

    $this->seed(CommunicationDeviceModelSeeder::class);

    $this->actingAs($admin)
        ->post(route('operations.shop.devices.store'), [
            'name' => 'Right VVX350',
            'mac_address' => '48256730757F',
            'model' => 'vvx350',
            'workstation_id' => $workstation->id,
            'provider' => CommunicationDeviceProvider::ShopPhone->value,
        ])
        ->assertRedirect();

    expect(TelephonyExtension::primaryForWorkstation($workstation->id)?->extension)->toBe('101');
});

test('ensure extension reclaims legacy orphan extension row for same number', function (): void {
    $this->withoutVite();

    $admin = User::factory()->create(['is_master_admin' => true])->assignRole(ArkRole::Admin->value);

    TelephonyExtension::query()->create([
        'extension' => '101',
        'display_name' => 'Legacy desk',
        'user_id' => $admin->id,
        'workstation_id' => null,
        'enabled' => true,
        'device_type' => TelephonyExtensionDeviceType::DeskPhone,
        'secret' => 'legacy-secret-101',
    ]);

    $this->actingAs($admin)
        ->post(route('operations.shop.workstations.store'), [
            'name' => 'Right',
        ])
        ->assertRedirect(route('operations.shop.communications'))
        ->assertSessionHas('status');

    $workstation = Workstation::query()->where('name', 'Right')->firstOrFail();
    $extension = TelephonyExtension::primaryForWorkstation($workstation->id);

    expect($extension?->extension)->toBe('101')
        ->and($extension?->display_name)->toBe('Right')
        ->and($extension?->user_id)->toBeNull()
        ->and(TelephonyExtension::query()->where('extension', '101')->count())->toBe(1);

    $this->actingAs($admin)
        ->get(route('operations.shop.communications'))
        ->assertOk()
        ->assertSee('Plug in a phone')
        ->assertSee('Waiting for a phone')
        ->assertDontSee('Assign extension');
});

test('assign extension rejects real conflict without mutating either workstation', function (): void {
    $this->withoutVite();

    $admin = User::factory()->create(['is_master_admin' => true])->assignRole(ArkRole::Admin->value);

    $workstationA = Workstation::query()->create([
        'shop_settings_id' => ShopSettings::reloadCurrent()->id,
        'name' => 'Right',
        'is_active' => true,
    ]);

    $workstationB = Workstation::query()->create([
        'shop_settings_id' => ShopSettings::reloadCurrent()->id,
        'name' => 'Left',
        'is_active' => true,
    ]);

    app(AssignExtensionToWorkstationAction::class)->execute(
        workstation: $workstationA,
        extension: '101',
        displayName: 'Right',
        secret: 'secret-101',
    );

    app(AssignExtensionToWorkstationAction::class)->execute(
        workstation: $workstationB,
        extension: '102',
        displayName: 'Left',
        secret: 'secret-102',
    );

    $this->actingAs($admin)
        ->followingRedirects()
        ->from(route('operations.shop.communications'))
        ->post(route('operations.shop.workstations.extension.assign', $workstationB), [
            'extension' => '101',
            'display_name' => 'Left',
        ])
        ->assertOk()
        ->assertSee('already assigned elsewhere', false);

    expect(TelephonyExtension::primaryForWorkstation($workstationA->id)?->extension)->toBe('101')
        ->and(TelephonyExtension::primaryForWorkstation($workstationB->id)?->extension)->toBe('102')
        ->and(TelephonyExtension::query()->where('extension', '101')->count())->toBe(1);

    $this->actingAs($admin)
        ->get(route('operations.shop.communications'))
        ->assertOk()
        ->assertDontSee('Assign extension');

    $deviceA = CommunicationDevice::query()->create([
        'shop_settings_id' => ShopSettings::reloadCurrent()->id,
        'workstation_id' => $workstationA->id,
        'name' => 'Right VVX350',
        'model' => 'VVX350',
        'mac_address' => '48256730757F',
        'provider' => CommunicationDeviceProvider::ShopPhone,
        'status' => CommunicationDeviceStatus::WaitingForRegistration,
        'capabilities' => ['voice'],
        'is_active' => true,
    ]);

    $deviceB = CommunicationDevice::query()->create([
        'shop_settings_id' => ShopSettings::reloadCurrent()->id,
        'workstation_id' => $workstationB->id,
        'name' => 'Left VVX350',
        'model' => 'VVX350',
        'mac_address' => '48256730758A',
        'provider' => CommunicationDeviceProvider::ShopPhone,
        'status' => CommunicationDeviceStatus::WaitingForRegistration,
        'capabilities' => ['voice'],
        'is_active' => true,
    ]);

    $readinessA = app(FirstContactReadinessProjection::class)->forDevice($deviceA->fresh());
    $readinessB = app(FirstContactReadinessProjection::class)->forDevice($deviceB->fresh());

    expect($readinessA['checks'][3])->toMatchArray(['label' => 'Extension assigned', 'passed' => true])
        ->and($readinessB['checks'][3])->toMatchArray(['label' => 'Extension assigned', 'passed' => true]);
});

test('shop communications surfaces attention when a device is offline', function (): void {
    $this->withoutVite();

    $admin = User::factory()->create(['name' => 'Edward Soares', 'is_master_admin' => true])->assignRole(ArkRole::Admin->value);

    ShopSettings::current()->persistTrusted([
        'telephony_inbound_number' => '7194136227',
    ]);

    CommunicationDevice::query()->create([
        'shop_settings_id' => ShopSettings::reloadCurrent()->id,
        'name' => 'Front Desk VVX450',
        'assigned_user_id' => $admin->id,
        'provider' => CommunicationDeviceProvider::ShopPhone,
        'status' => CommunicationDeviceStatus::WaitingForRegistration,
        'capabilities' => ['voice'],
    ]);

    $this->actingAs($admin)
        ->get(route('operations.shop.communications'))
        ->assertOk()
        ->assertSee('Needs attention')
        ->assertSee('Attention')
        ->assertSee('Front Desk VVX450 offline');
});

test('legacy communications shop url redirects to shop communications', function (): void {
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);

    $this->actingAs($admin)
        ->get('/app/communications/shop')
        ->assertRedirect('/app/shop/communications');
});

test('person workspace lists assigned devices with name-only add form', function (): void {
    $admin = User::factory()->create(['name' => 'Molly Advisor'])->assignRole(ArkRole::Admin->value);

    $this->actingAs($admin)
        ->get(route('operations.shop.people.show', $admin))
        ->assertOk()
        ->assertSee('Assigned Devices')
        ->assertSee('Add Device');

    $response = $this->actingAs($admin)
        ->post(route('operations.shop.devices.store'), [
            'name' => 'Front Desk VVX450',
            'assigned_user_id' => $admin->id,
            'provider' => CommunicationDeviceProvider::ShopPhone->value,
        ]);

    $device = CommunicationDevice::query()->where('assigned_user_id', $admin->id)->firstOrFail();

    $response->assertRedirect(route('operations.shop.devices.show', $device));

    expect(CommunicationDevice::query()->where('assigned_user_id', $admin->id)->count())->toBe(1)
        ->and(CommunicationDevice::query()->where('assigned_user_id', $admin->id)->value('status'))
        ->toBe(CommunicationDeviceStatus::WaitingForRegistration);
});

test('device workspace tells operational truth without transport details', function (): void {
    $admin = User::factory()->create(['name' => 'Edward Soares', 'is_master_admin' => true])->assignRole(ArkRole::Admin->value);

    $registeredAt = ShopDisplayTimezone::now()->setTime(9, 14);

    $device = CommunicationDevice::query()->create([
        'shop_settings_id' => ShopSettings::reloadCurrent()->id,
        'name' => 'Front Desk VVX450',
        'assigned_user_id' => $admin->id,
        'provider' => CommunicationDeviceProvider::ShopPhone,
        'provider_identifier' => 'hidden-identity-001',
        'status' => CommunicationDeviceStatus::Connected,
        'last_registered_at' => $registeredAt,
        'microbrowser_token' => 'screen-token-abc',
        'capabilities' => ['voice'],
    ]);

    $this->actingAs($admin)
        ->get(route('operations.shop.devices.show', $device))
        ->assertOk()
        ->assertSee('Front Desk VVX450')
        ->assertSee('Status')
        ->assertSee('Connected')
        ->assertSee('Current operator')
        ->assertSee('Not signed in')
        ->assertSee('Right now')
        ->assertSee('Idle')
        ->assertSee('Last call')
        ->assertSee('Infrastructure')
        ->assertSee('Generate config')
        ->assertDontSee('Assigned')
        ->assertDontSee('hidden-identity-001')
        ->assertDontSee('SIP')
        ->assertDontSee('Extension');
});

test('device provisioning controls stay hidden from non master admins', function (): void {
    $admin = User::factory()->create(['name' => 'Edward Soares', 'is_master_admin' => false])->assignRole(ArkRole::Admin->value);

    $device = CommunicationDevice::query()->create([
        'shop_settings_id' => ShopSettings::reloadCurrent()->id,
        'name' => 'Front Desk VVX450',
        'assigned_user_id' => $admin->id,
        'provider' => CommunicationDeviceProvider::ShopPhone,
        'status' => CommunicationDeviceStatus::WaitingForRegistration,
        'capabilities' => ['voice'],
    ]);

    $this->actingAs($admin)
        ->get(route('operations.shop.devices.show', $device))
        ->assertOk()
        ->assertSee('Current operator')
        ->assertDontSee('Infrastructure')
        ->assertDontSee('Generate config');
});

test('device provisioning generates downloadable config without exposing credentials in ui', function (): void {
    Storage::fake('local');

    config()->set('shop.base_url', 'https://shop.test');
    config()->set('voice-transport.sip_registrar', 'voice.shop.test');
    config()->set('voice-transport.sip_port', 5060);
    config()->set('telephony.sip_provisioning.default_password', 'secret-101');

    $admin = User::factory()->create(['name' => 'Edward Soares', 'is_master_admin' => true])->assignRole(ArkRole::Admin->value);

    $workstation = Workstation::query()->create([
        'shop_settings_id' => ShopSettings::reloadCurrent()->id,
        'name' => 'Front Counter',
        'is_active' => true,
    ]);

    $device = CommunicationDevice::query()->create([
        'shop_settings_id' => ShopSettings::reloadCurrent()->id,
        'workstation_id' => $workstation->id,
        'name' => 'Front Desk VVX450',
        'model' => 'VVX450',
        'mac_address' => '48256730757F',
        'provider' => CommunicationDeviceProvider::ShopPhone,
        'status' => CommunicationDeviceStatus::WaitingForRegistration,
        'microbrowser_token' => 'screen-token-abc',
        'capabilities' => ['voice'],
        'is_active' => true,
    ]);

    app(AssignExtensionToWorkstationAction::class)->execute(
        workstation: $workstation,
        extension: '101',
        displayName: 'Front Counter',
        secret: 'secret-101',
        communicationDevice: $device,
    );

    $this->actingAs($admin)
        ->post(route('operations.shop.devices.provision.generate', $device))
        ->assertRedirect(route('operations.shop.devices.show', $device));

    $device->refresh();

    expect($device->status)->toBe(CommunicationDeviceStatus::Provisioning)
        ->and($device->provider_identifier)->toBe('101')
        ->and($device->hasProvisionConfig())->toBeTrue();

    $this->actingAs($admin)
        ->get(route('operations.shop.devices.show', $device))
        ->assertOk()
        ->assertSee('Download config')
        ->assertDontSee('secret-101');

    $this->actingAs($admin)
        ->get(route('operations.shop.devices.provision.download', $device))
        ->assertOk()
        ->assertDownload('front-desk-vvx450.cfg');
});

test('device provisioning download is restricted to settings managers', function (): void {
    Storage::fake('local');

    config()->set('shop.base_url', 'https://shop.test');
    config()->set('voice-transport.sip_registrar', 'voice.shop.test');
    config()->set('voice-transport.sip_port', 5060);
    config()->set('telephony.sip_provisioning.default_password', 'secret-101');

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $device = CommunicationDevice::query()->create([
        'shop_settings_id' => ShopSettings::reloadCurrent()->id,
        'name' => 'Front Desk VVX450',
        'assigned_user_id' => $advisor->id,
        'provider' => CommunicationDeviceProvider::ShopPhone,
        'status' => CommunicationDeviceStatus::Provisioning,
        'capabilities' => ['voice'],
    ]);

    Storage::disk('local')->put($device->provisionConfigPath(), 'reg.1.auth.password="secret-101"');

    $this->actingAs($advisor)
        ->get(route('operations.shop.devices.provision.download', $device))
        ->assertForbidden();
});

test('incoming routing updates ring targets by person not extension', function (): void {
    $admin = User::factory()->create(['name' => 'Edward Soares'])->assignRole(ArkRole::Admin->value);
    $molly = User::factory()->create(['name' => 'Molly Advisor'])->assignRole(ArkRole::Advisor->value);

    $edwardEndpoint = TelephonyEndpoint::query()->create([
        'name' => 'Edward',
        'type' => TelephonyEndpointType::Cell,
        'destination' => '7195550101',
        'user_id' => $admin->id,
        'enabled' => true,
        'position' => 1,
    ]);

    $mollyEndpoint = TelephonyEndpoint::query()->create([
        'name' => 'Molly',
        'type' => TelephonyEndpointType::Cell,
        'destination' => '7195550102',
        'user_id' => $molly->id,
        'enabled' => true,
        'position' => 2,
    ]);

    $this->actingAs($admin)
        ->patch(route('operations.shop.communications.incoming-routing.update'), [
            'ring_user_ids' => [$admin->id],
        ])
        ->assertRedirect(route('operations.shop.communications'));

    expect($edwardEndpoint->fresh()->enabled)->toBeTrue()
        ->and($mollyEndpoint->fresh()->enabled)->toBeFalse();
});

test('shop communications manual device entry is limited to master admin support', function (): void {
    $admin = User::factory()->create(['name' => 'Edward Soares', 'is_master_admin' => true])->assignRole(ArkRole::Admin->value);

    $this->seed(CommunicationDeviceModelSeeder::class);

    $this->actingAs($admin)
        ->get(route('operations.shop.communications'))
        ->assertOk()
        ->assertSee('Manual device entry (support)')
        ->assertSee('MAC address')
        ->assertSee('VVX350')
        ->assertSee('VVX450');
});

test('store communication device persists normalized mac and resolved model', function (): void {
    $admin = User::factory()->create(['name' => 'Edward Soares'])->assignRole(ArkRole::Admin->value);

    $this->seed(CommunicationDeviceModelSeeder::class);

    $this->actingAs($admin)
        ->post(route('operations.shop.devices.store'), [
            'name' => 'Bench VVX350',
            'mac_address' => '48:25:67:30:75:7f',
            'model' => 'vvx350',
            'provider' => CommunicationDeviceProvider::ShopPhone->value,
        ])
        ->assertRedirect();

    $device = CommunicationDevice::query()->where('name', 'Bench VVX350')->firstOrFail();

    expect($device->mac_address)->toBe('48256730757F')
        ->and($device->communication_device_model_id)->not->toBeNull()
        ->and($device->deviceModel?->slug)->toBe('vvx350');
});

test('device workspace surfaces provisioning observability for bench certification', function (): void {
    $this->withoutVite();

    config()->set('shop.base_url', 'https://shop.test');
    config()->set('telephony.sip_provisioning.host', 'voice.lugsnplugs.com');
    config()->set('telephony.sip_provisioning.default_password', 'secret-101');

    $admin = User::factory()->create(['name' => 'Edward Soares', 'is_master_admin' => true])->assignRole(ArkRole::Admin->value);

    $this->seed(CommunicationDeviceModelSeeder::class);

    $workstation = Workstation::query()->create([
        'shop_settings_id' => ShopSettings::reloadCurrent()->id,
        'name' => 'Front Counter',
        'is_active' => true,
    ]);

    $device = CommunicationDevice::query()->create([
        'shop_settings_id' => ShopSettings::reloadCurrent()->id,
        'workstation_id' => $workstation->id,
        'name' => 'Bench VVX350',
        'mac_address' => '48256730757F',
        'model' => 'VVX350',
        'provider' => CommunicationDeviceProvider::ShopPhone,
        'status' => CommunicationDeviceStatus::WaitingForRegistration,
        'is_active' => true,
        'capabilities' => ['voice'],
    ]);

    app(AssignExtensionToWorkstationAction::class)->execute(
        workstation: $workstation,
        extension: '101',
        displayName: 'Front Counter',
        secret: 'secret-101',
        communicationDevice: $device,
    );

    app(RegenerateEndpointConfigurationAction::class)->execute($device->fresh());

    $this->actingAs($admin)
        ->get(route('operations.shop.devices.show', $device))
        ->assertOk()
        ->assertSee('Begin First Contact')
        ->assertSee('Certification · G1–G7')
        ->assertSee('Provisioning')
        ->assertSee('48:25:67:30:75:7F')
        ->assertSee('VVX350')
        ->assertSee('Current')
        ->assertSee('Custom provisioning server')
        ->assertSee('Leave blank')
        ->assertSee('https://shop.test/provision/')
        ->assertSee('48256730757F.cfg')
        ->assertSee('Current projection body')
        ->assertSee('reg.1.server.1.address="voice.lugsnplugs.com"');
});

test('device projection preview stays hidden from non master admins', function (): void {
    config()->set('telephony.sip_provisioning.host', 'voice.lugsnplugs.com');
    config()->set('telephony.sip_provisioning.default_password', 'secret-101');

    $admin = User::factory()->create(['name' => 'Edward Soares', 'is_master_admin' => false])->assignRole(ArkRole::Admin->value);

    $this->seed(CommunicationDeviceModelSeeder::class);

    $workstation = Workstation::query()->create([
        'shop_settings_id' => ShopSettings::reloadCurrent()->id,
        'name' => 'Front Counter',
        'is_active' => true,
    ]);

    $device = CommunicationDevice::query()->create([
        'shop_settings_id' => ShopSettings::reloadCurrent()->id,
        'workstation_id' => $workstation->id,
        'name' => 'Bench VVX350',
        'mac_address' => '48256730757F',
        'model' => 'VVX350',
        'provider' => CommunicationDeviceProvider::ShopPhone,
        'status' => CommunicationDeviceStatus::WaitingForRegistration,
        'is_active' => true,
        'capabilities' => ['voice'],
    ]);

    app(AssignExtensionToWorkstationAction::class)->execute(
        workstation: $workstation,
        extension: '101',
        displayName: 'Front Counter',
        secret: 'secret-101',
        communicationDevice: $device,
    );

    app(RegenerateEndpointConfigurationAction::class)->execute($device->fresh());

    $this->actingAs($admin)
        ->get(route('operations.shop.devices.show', $device))
        ->assertOk()
        ->assertSee('Provisioning')
        ->assertSee('48:25:67:30:75:7F')
        ->assertSee('Provision URL')
        ->assertDontSee('Current projection body')
        ->assertDontSee('Infrastructure');
});

test('store communication device rejects invalid mac with validation error', function (): void {
    $this->withoutVite();

    $admin = User::factory()->create(['is_master_admin' => true])->assignRole(ArkRole::Admin->value);

    $workstation = Workstation::query()->create([
        'shop_settings_id' => ShopSettings::reloadCurrent()->id,
        'name' => 'Front Counter',
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->from(route('operations.shop.communications'))
        ->post(route('operations.shop.devices.store'), [
            'name' => 'Bad MAC Phone',
            'mac_address' => 'not-a-mac',
            'model' => 'VVX350',
            'workstation_id' => $workstation->id,
            'provider' => CommunicationDeviceProvider::ShopPhone->value,
        ])
        ->assertRedirect(route('operations.shop.communications'))
        ->assertSessionHasErrors('mac_address');

    expect(CommunicationDevice::query()->where('name', 'Bad MAC Phone')->exists())->toBeFalse();
});

test('store communication device rejects duplicate mac with validation error', function (): void {
    $this->withoutVite();

    $admin = User::factory()->create(['is_master_admin' => true])->assignRole(ArkRole::Admin->value);

    $this->seed(CommunicationDeviceModelSeeder::class);

    $workstation = Workstation::query()->create([
        'shop_settings_id' => ShopSettings::reloadCurrent()->id,
        'name' => 'Front Counter',
        'is_active' => true,
    ]);

    CommunicationDevice::query()->create([
        'shop_settings_id' => ShopSettings::reloadCurrent()->id,
        'workstation_id' => $workstation->id,
        'name' => 'Existing Phone',
        'mac_address' => '48256730757F',
        'model' => 'VVX350',
        'provider' => CommunicationDeviceProvider::ShopPhone,
        'status' => CommunicationDeviceStatus::WaitingForRegistration,
        'capabilities' => ['voice'],
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->from(route('operations.shop.communications'))
        ->post(route('operations.shop.devices.store'), [
            'name' => 'Duplicate MAC Phone',
            'mac_address' => '48:25:67:30:75:7F',
            'model' => 'vvx350',
            'workstation_id' => $workstation->id,
            'provider' => CommunicationDeviceProvider::ShopPhone->value,
        ])
        ->assertRedirect(route('operations.shop.communications'))
        ->assertSessionHasErrors('mac_address');
});

test('adding phone to workstation opens device workspace without server error', function (): void {
    $this->withoutVite();

    $admin = User::factory()->create(['is_master_admin' => true])->assignRole(ArkRole::Admin->value);

    $this->seed(CommunicationDeviceModelSeeder::class);

    $workstation = Workstation::query()->create([
        'shop_settings_id' => ShopSettings::reloadCurrent()->id,
        'name' => 'Front Counter',
        'is_active' => true,
    ]);

    $response = $this->actingAs($admin)
        ->post(route('operations.shop.devices.store'), [
            'name' => 'Front Counter VVX350',
            'mac_address' => '48256730757F',
            'model' => 'vvx350',
            'workstation_id' => $workstation->id,
            'provider' => CommunicationDeviceProvider::ShopPhone->value,
        ])
        ->assertRedirect();

    $device = CommunicationDevice::query()->where('name', 'Front Counter VVX350')->firstOrFail();

    $response->assertRedirect(route('operations.shop.devices.show', $device));

    $this->actingAs($admin)
        ->get(route('operations.shop.devices.show', $device))
        ->assertOk()
        ->assertSee('Front Counter VVX350')
        ->assertSee('First Contact');
});

test('settings manager can remove a communication device and return to voice setup', function (): void {
    $this->withoutVite();

    $admin = User::factory()->create(['is_master_admin' => true])->assignRole(ArkRole::Admin->value);

    $this->seed(CommunicationDeviceModelSeeder::class);

    $workstation = Workstation::query()->create([
        'shop_settings_id' => ShopSettings::reloadCurrent()->id,
        'name' => 'Front Counter',
        'is_active' => true,
    ]);

    $device = CommunicationDevice::query()->create([
        'shop_settings_id' => ShopSettings::reloadCurrent()->id,
        'workstation_id' => $workstation->id,
        'name' => 'Front Counter phone',
        'mac_address' => '48256730757F',
        'model' => 'VVX350',
        'provider' => CommunicationDeviceProvider::ShopPhone,
        'status' => CommunicationDeviceStatus::WaitingForRegistration,
        'capabilities' => ['voice'],
        'is_active' => true,
    ]);

    TelephonyExtension::query()->create([
        'extension' => '101',
        'display_name' => 'Front Counter',
        'workstation_id' => $workstation->id,
        'communication_device_id' => $device->id,
        'enabled' => true,
        'device_type' => TelephonyExtensionDeviceType::DeskPhone,
        'secret' => 'secret-101',
    ]);

    $this->actingAs($admin)
        ->delete(route('operations.shop.devices.destroy', $device))
        ->assertRedirect(route('operations.shop.communications'))
        ->assertSessionHas('status');

    expect(CommunicationDevice::query()->whereKey($device->id)->exists())->toBeFalse()
        ->and(TelephonyExtension::query()->where('workstation_id', $workstation->id)->where('extension', '101')->exists())->toBeTrue();

    $this->actingAs($admin)
        ->get(route('operations.shop.communications'))
        ->assertOk()
        ->assertSee('Plug in a phone')
        ->assertSee('Waiting for a phone')
        ->assertDontSee('Remove phone');
});

test('settings manager can update workstation name and location', function (): void {
    $this->withoutVite();

    $admin = User::factory()->create(['is_master_admin' => true])->assignRole(ArkRole::Admin->value);

    $workstation = Workstation::query()->create([
        'shop_settings_id' => ShopSettings::reloadCurrent()->id,
        'name' => 'Right',
        'location_label' => 'Front desk',
        'is_active' => true,
    ]);

    TelephonyExtension::query()->create([
        'extension' => '101',
        'display_name' => 'Right',
        'workstation_id' => $workstation->id,
        'enabled' => true,
        'device_type' => TelephonyExtensionDeviceType::DeskPhone,
        'secret' => 'secret-101',
    ]);

    $this->actingAs($admin)
        ->patch(route('operations.shop.workstations.update', $workstation), [
            'name' => 'Service Counter',
            'location_label' => 'Bay 1',
            'edit_workstation_id' => $workstation->id,
        ])
        ->assertRedirect(route('operations.shop.communications'))
        ->assertSessionHas('status');

    $workstation->refresh();

    expect($workstation->name)->toBe('Service Counter')
        ->and($workstation->location_label)->toBe('Bay 1')
        ->and(TelephonyExtension::primaryForWorkstation($workstation->id)?->display_name)->toBe('Service Counter');
});

test('settings manager renaming a station preserves schedule bay flag', function (): void {
    $this->withoutVite();

    $admin = User::factory()->create(['is_master_admin' => true])->assignRole(ArkRole::Admin->value);

    $workstation = Workstation::query()->create([
        'shop_settings_id' => ShopSettings::reloadCurrent()->id,
        'name' => 'Bay 2',
        'is_active' => true,
        'accepts_scheduled_work' => true,
    ]);

    $this->actingAs($admin)
        ->patch(route('operations.shop.workstations.update', $workstation), [
            'name' => 'Lift 2',
            'location_label' => '',
            'edit_workstation_id' => $workstation->id,
        ])
        ->assertRedirect(route('operations.shop.communications'));

    expect($workstation->fresh()->accepts_scheduled_work)->toBeTrue()
        ->and($workstation->fresh()->name)->toBe('Lift 2');
});

test('settings manager can delete empty workstation and free extension', function (): void {
    $this->withoutVite();

    $admin = User::factory()->create(['is_master_admin' => true])->assignRole(ArkRole::Admin->value);

    $workstation = Workstation::query()->create([
        'shop_settings_id' => ShopSettings::reloadCurrent()->id,
        'name' => 'Bench Test',
        'is_active' => true,
    ]);

    TelephonyExtension::query()->create([
        'extension' => '102',
        'display_name' => 'Bench Test',
        'workstation_id' => $workstation->id,
        'enabled' => true,
        'device_type' => TelephonyExtensionDeviceType::DeskPhone,
        'secret' => 'secret-102',
    ]);

    $this->actingAs($admin)
        ->delete(route('operations.shop.workstations.destroy', $workstation))
        ->assertRedirect(route('operations.shop.communications'))
        ->assertSessionHas('status');

    expect(Workstation::query()->whereKey($workstation->id)->exists())->toBeFalse()
        ->and(TelephonyExtension::query()->where('extension', '102')->exists())->toBeFalse();
});

test('deleting workstation with phones attached is blocked', function (): void {
    $admin = User::factory()->create(['is_master_admin' => true])->assignRole(ArkRole::Admin->value);

    $this->seed(CommunicationDeviceModelSeeder::class);

    $workstation = Workstation::query()->create([
        'shop_settings_id' => ShopSettings::reloadCurrent()->id,
        'name' => 'Right',
        'is_active' => true,
    ]);

    CommunicationDevice::query()->create([
        'shop_settings_id' => ShopSettings::reloadCurrent()->id,
        'workstation_id' => $workstation->id,
        'name' => 'Right phone',
        'mac_address' => '48256730757F',
        'model' => 'VVX350',
        'provider' => CommunicationDeviceProvider::ShopPhone,
        'status' => CommunicationDeviceStatus::WaitingForRegistration,
        'capabilities' => ['voice'],
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->from(route('operations.shop.communications'))
        ->delete(route('operations.shop.workstations.destroy', $workstation))
        ->assertRedirect(route('operations.shop.communications'))
        ->assertSessionHasErrors('workstation');

    expect(Workstation::query()->whereKey($workstation->id)->exists())->toBeTrue();
});
