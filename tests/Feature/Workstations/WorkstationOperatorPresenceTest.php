<?php

use App\Ark\Operations\Communications\CommunicationDevice;
use App\Ark\Operations\Communications\CommunicationDeviceProvider;
use App\Ark\Operations\Communications\CommunicationDeviceStatus;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Telephony\TelephonyExtension;
use App\Ark\Operations\Telephony\TelephonyExtensionDeviceType;
use App\Ark\Operations\Workstations\OperatorPinVerifier;
use App\Ark\Operations\Workstations\Workstation;
use App\Ark\Operations\Workstations\WorkstationBrowserBinding;
use App\Ark\Operations\Workstations\WorkstationBrowserRoster;
use App\Ark\Operations\Workstations\WorkstationPresence;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

beforeEach(function (): void {
    Cache::flush();
    $this->seed(ArkAuthorizationSeeder::class);
    ShopSettings::forgetCurrent();
    ShopSettings::current();
});

/**
 * @param  TestCase  $test
 */
function workstationBindingCookies(WorkstationBrowserBinding $binding): array
{
    return [WorkstationPresence::BINDING_COOKIE => $binding->token];
}

/**
 * @param  TestCase  $test
 */
function workstationFormCall(
    $test,
    string $uri,
    User $user,
    WorkstationBrowserBinding $binding,
    array $data = [],
): TestResponse {
    return $test->actingAs($user)->call(
        'POST',
        $uri,
        $data,
        workstationBindingCookies($binding),
    );
}

/**
 * @param  TestCase  $test
 */
function workstationJsonCall(
    $test,
    string $method,
    string $uri,
    User $user,
    WorkstationBrowserBinding $binding,
    array $data = [],
): TestResponse {
    return $test->actingAs($user)->call(
        $method,
        $uri,
        $data,
        workstationBindingCookies($binding),
        [],
        ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'],
    );
}

/**
 * @param  TestCase  $test
 */
function workstationGet(
    $test,
    string $uri,
    User $user,
    ?WorkstationBrowserBinding $binding = null,
    array $session = [],
): TestResponse {
    $test = $test->actingAs($user);

    if ($session !== []) {
        $test = $test->withSession($session);
    }

    return $test->call(
        'GET',
        $uri,
        [],
        $binding ? workstationBindingCookies($binding) : [],
    );
}

test('first-time operator creates workstation pin with password and signs into station', function (): void {
    $admin = User::factory()->create([
        'name' => 'Alex Rivera',
        'password' => bcrypt('password'),
    ])->assignRole(ArkRole::Admin->value);

    $workstation = Workstation::query()->create([
        'shop_settings_id' => ShopSettings::reloadCurrent()->id,
        'name' => 'Front Counter',
        'is_active' => true,
    ]);

    $binding = WorkstationBrowserBinding::issueForWorkstation($workstation);

    expect($admin->hasOperatorPin())->toBeFalse();

    $response = workstationJsonCall($this, 'POST', '/app/workstation/pin', $admin, $binding, [
        'password' => 'password',
        'pin' => '1234',
        'pin_confirmation' => '1234',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('operator.name', 'Alex Rivera');

    $admin->refresh();
    $workstation->refresh();

    expect($admin->hasOperatorPin())->toBeTrue()
        ->and($workstation->current_operator_user_id)->toBe($admin->id)
        ->and(auth()->id())->toBe($admin->id);
});

test('operator can change workstation pin with password confirmation', function (): void {
    $admin = User::factory()->create([
        'password' => bcrypt('password'),
    ])->assignRole(ArkRole::Admin->value);
    $admin->setOperatorPin('1234');

    $this->actingAs($admin)
        ->patchJson(route('operations.workstation.pin.update'), [
            'password' => 'password',
            'pin' => '9876',
            'pin_confirmation' => '9876',
        ])
        ->assertOk()
        ->assertJsonPath('updated', true);

    expect(app(OperatorPinVerifier::class)->verify($admin->fresh(), '9876'))->toBeTrue()
        ->and(app(OperatorPinVerifier::class)->verify($admin, '1234'))->toBeFalse();
});

test('operator can change workstation pin from profile settings', function (): void {
    $admin = User::factory()->create([
        'password' => bcrypt('password'),
    ])->assignRole(ArkRole::Admin->value);
    $admin->setOperatorPin('1234');

    $this->actingAs($admin)
        ->patch(route('profile.workstation-pin.update'), [
            'password' => 'password',
            'pin' => '4321',
            'pin_confirmation' => '4321',
        ])
        ->assertRedirect(route('profile.edit', ['tab' => 'workstation-pin']))
        ->assertSessionHas('status', 'workstation-pin-updated');

    expect(app(OperatorPinVerifier::class)->verify($admin->fresh(), '4321'))->toBeTrue();
});

test('workstation lock screen retired — operational shell stays visible', function (): void {
    $this->withoutVite();

    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
    $admin->setOperatorPin('1234');

    $workstation = Workstation::query()->create([
        'shop_settings_id' => ShopSettings::reloadCurrent()->id,
        'name' => 'Front Counter',
        'is_active' => true,
    ]);

    $binding = WorkstationBrowserBinding::issueForWorkstation($workstation);

    workstationGet($this, '/app', $admin, $binding, [WorkstationPresence::SESSION_LOCKED => true])
        ->assertOk()
        ->assertSee('name="ark-workstation-privacy-active" content="0"', false)
        ->assertDontSee('Find advisor', false)
        ->assertSee('ops-left-rail', false);

    $this->actingAs($admin)
        ->withSession([WorkstationPresence::SESSION_LOCKED => true])
        ->call(
            'GET',
            '/app/api/comms/interrupts',
            [],
            workstationBindingCookies($binding),
            [],
            ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'],
        )
        ->assertOk()
        ->assertJsonPath('summary.station_privacy_active', false);
});

test('workstation does not prompt for pin setup on load', function (): void {
    $this->withoutVite();

    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);

    $workstation = Workstation::query()->create([
        'shop_settings_id' => ShopSettings::reloadCurrent()->id,
        'name' => 'Front Counter',
        'is_active' => true,
    ]);

    $binding = WorkstationBrowserBinding::issueForWorkstation($workstation);

    workstationGet($this, '/app/shop/communications', $admin, $binding)
        ->assertOk()
        ->assertSee('data-needs-pin-setup="0"', false)
        ->assertDontSee('Set up your PIN', false)
        ->assertDontSee('Create PIN', false)
        ->assertDontSee('Step away', false);
});

test('workstation staff list includes avatar metadata for operator tiles', function (): void {
    $admin = User::factory()->create([
        'name' => 'Alex Rivera',
        'accent_color' => '#ff6600',
    ])->assignRole(ArkRole::Admin->value);
    $admin->setOperatorPin('1234');

    $workstation = Workstation::query()->create([
        'shop_settings_id' => ShopSettings::reloadCurrent()->id,
        'name' => 'Front Counter',
        'is_active' => true,
    ]);

    $binding = WorkstationBrowserBinding::issueForWorkstation($workstation);
    app(WorkstationBrowserRoster::class)->remember($binding, $admin);

    workstationJsonCall($this, 'GET', '/app/api/workstation/staff', $admin, $binding)
        ->assertOk()
        ->assertJsonFragment([
            'id' => $admin->id,
            'name' => 'Alex Rivera',
            'initials' => 'ES',
            'avatar_color' => '#ff6600',
            'has_pin' => true,
        ]);
});

test('unlocking workstation sets current operator across session and workstation', function (): void {
    $admin = User::factory()->create(['name' => 'Alex Rivera'])->assignRole(ArkRole::Admin->value);
    $admin->setOperatorPin('1234');

    $molly = User::factory()->create(['name' => 'Molly Soares'])->assignRole(ArkRole::Advisor->value);
    $molly->setOperatorPin('5678');

    $workstation = Workstation::query()->create([
        'shop_settings_id' => ShopSettings::reloadCurrent()->id,
        'name' => 'Front Counter',
        'location_label' => 'Front desk',
        'is_active' => true,
    ]);

    $device = CommunicationDevice::query()->create([
        'shop_settings_id' => ShopSettings::reloadCurrent()->id,
        'workstation_id' => $workstation->id,
        'name' => 'Front Counter Left',
        'model' => 'VVX450',
        'provider' => CommunicationDeviceProvider::ShopPhone,
        'status' => CommunicationDeviceStatus::Connected,
        'capabilities' => ['voice'],
    ]);

    $binding = WorkstationBrowserBinding::issueForWorkstation($workstation);
    $roster = app(WorkstationBrowserRoster::class);
    $roster->remember($binding, $admin);
    $roster->remember($binding, $molly);

    workstationJsonCall($this, 'POST', '/app/workstation/unlock', $admin, $binding, [
        'user_id' => $admin->id,
        'pin' => '1234',
    ])
        ->assertOk()
        ->assertJsonPath('operator.name', 'Alex Rivera');

    $workstation->refresh();
    expect($workstation->current_operator_user_id)->toBe($admin->id)
        ->and(auth()->id())->toBe($admin->id);

    workstationJsonCall($this, 'POST', '/app/workstation/unlock', $admin, $binding, [
        'user_id' => $molly->id,
        'pin' => '5678',
    ])
        ->assertOk();

    expect(auth()->id())->toBe($molly->id)
        ->and($workstation->fresh()->current_operator_user_id)->toBe($molly->id);
});

test('workstation lock endpoint clears legacy lock session', function (): void {
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
    $admin->setOperatorPin('1234');

    $workstation = Workstation::query()->create([
        'shop_settings_id' => ShopSettings::reloadCurrent()->id,
        'name' => 'Front Counter',
        'is_active' => true,
        'current_operator_user_id' => $admin->id,
    ]);

    $binding = WorkstationBrowserBinding::issueForWorkstation($workstation);

    $this->actingAs($admin)
        ->withSession([WorkstationPresence::SESSION_LOCKED => true])
        ->call(
            'POST',
            '/app/workstation/lock',
            [],
            workstationBindingCookies($binding),
        )
        ->assertRedirect();

    expect(session(WorkstationPresence::SESSION_LOCKED))->toBeNull();
});

test('binding browser to workstation persists via cookie', function (): void {
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);

    $workstation = Workstation::query()->create([
        'shop_settings_id' => ShopSettings::reloadCurrent()->id,
        'name' => 'Service Desk',
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->post(route('operations.workstation.bind'), [
            'workstation_id' => $workstation->id,
        ])
        ->assertRedirect()
        ->assertCookie(WorkstationPresence::BINDING_COOKIE);

    expect(WorkstationBrowserBinding::query()->where('workstation_id', $workstation->id)->exists())->toBeTrue();
    expect($workstation->fresh()->current_operator_user_id)->toBe($admin->id);
});

test('not now dismisses station bind prompt with a long lived cookie', function (): void {
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);

    $this->actingAs($admin)
        ->postJson(route('operations.workstation.bind.dismiss'))
        ->assertNoContent()
        ->assertPlainCookie(WorkstationPresence::DISMISS_COOKIE, '1');

    expect(session(WorkstationPresence::SESSION_BIND_DISMISSED))->toBeTrue();

    $this->flushSession();

    $this->actingAs($admin)
        ->withUnencryptedCookie(WorkstationPresence::DISMISS_COOKIE, '1')
        ->get('/app')
        ->assertOk()
        ->assertDontSee('data-ws-bind-panel', false)
        ->assertDontSee('Choose the station for this browser.', false);
});

test('binding a station clears bind dismiss cookie so prompts can return on unbound browsers', function (): void {
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);

    $workstation = Workstation::query()->create([
        'shop_settings_id' => ShopSettings::reloadCurrent()->id,
        'name' => 'Service Desk',
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->withUnencryptedCookie(WorkstationPresence::DISMISS_COOKIE, '1')
        ->post(route('operations.workstation.bind'), [
            'workstation_id' => $workstation->id,
        ])
        ->assertRedirect()
        ->assertPlainCookie(WorkstationPresence::DISMISS_COOKIE);
});

test('shop communications lists workstations with current operator', function (): void {
    $admin = User::factory()->create(['name' => 'Alex Rivera'])->assignRole(ArkRole::Admin->value);
    $admin->setOperatorPin('1234');

    $workstation = Workstation::query()->create([
        'shop_settings_id' => ShopSettings::reloadCurrent()->id,
        'name' => 'Front Counter',
        'is_active' => true,
        'current_operator_user_id' => $admin->id,
    ]);

    $binding = WorkstationBrowserBinding::issueForWorkstation($workstation);
    app(WorkstationBrowserRoster::class)->remember($binding, $admin);

    CommunicationDevice::query()->create([
        'shop_settings_id' => ShopSettings::reloadCurrent()->id,
        'workstation_id' => $workstation->id,
        'name' => 'Front Counter Left',
        'provider' => CommunicationDeviceProvider::ShopPhone,
        'status' => CommunicationDeviceStatus::Connected,
        'capabilities' => ['voice'],
    ]);

    workstationGet($this, '/app/shop/communications', $admin, $binding)
        ->assertOk()
        ->assertSee('Front Counter');
});

test('advisor can unlock shared station as admin with admin pin when admin is on roster', function (): void {
    $admin = User::factory()->create(['name' => 'Alex Rivera'])->assignRole(ArkRole::Admin->value);
    $admin->setOperatorPin('1234');

    $advisor = User::factory()->create(['name' => 'Morgan Lane'])->assignRole(ArkRole::Advisor->value);
    $advisor->setOperatorPin('9999');

    $workstation = Workstation::query()->create([
        'shop_settings_id' => ShopSettings::reloadCurrent()->id,
        'name' => 'Right',
        'is_active' => true,
    ]);

    $binding = WorkstationBrowserBinding::issueForWorkstation($workstation);
    $roster = app(WorkstationBrowserRoster::class);
    $roster->remember($binding, $advisor);
    $roster->remember($binding, $admin);

    workstationFormCall($this, '/app/workstation/unlock', $advisor, $binding, [
        'user_id' => $admin->id,
        'pin' => '1234',
    ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(auth()->id())->toBe($admin->id)
        ->and($workstation->fresh()->current_operator_user_id)->toBe($admin->id);
});

test('operator not on browser roster cannot unlock even with correct pin', function (): void {
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
    $admin->setOperatorPin('1234');

    $molly = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $molly->setOperatorPin('5678');

    $workstation = Workstation::query()->create([
        'shop_settings_id' => ShopSettings::reloadCurrent()->id,
        'name' => 'Front Counter',
        'is_active' => true,
    ]);

    $binding = WorkstationBrowserBinding::issueForWorkstation($workstation);
    app(WorkstationBrowserRoster::class)->remember($binding, $admin);

    $response = workstationFormCall($this, '/app/workstation/unlock', $admin, $binding, [
        'user_id' => $molly->id,
        'pin' => '5678',
    ]);

    expect(auth()->id())->toBe($admin->id);
    $response->assertRedirect();
    $response->assertSessionHasErrors('pin');
});

test('failed pin attempts are rate limited per binding and operator', function (): void {
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
    $admin->setOperatorPin('1234');

    $workstation = Workstation::query()->create([
        'shop_settings_id' => ShopSettings::reloadCurrent()->id,
        'name' => 'Front Counter',
        'is_active' => true,
    ]);

    $binding = WorkstationBrowserBinding::issueForWorkstation($workstation);
    app(WorkstationBrowserRoster::class)->remember($binding, $admin);

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $response = workstationFormCall($this, '/app/workstation/unlock', $admin, $binding, [
            'user_id' => $admin->id,
            'pin' => '0000',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('pin');
    }

    $response = workstationFormCall($this, '/app/workstation/unlock', $admin, $binding, [
        'user_id' => $admin->id,
        'pin' => '1234',
    ]);

    $response->assertRedirect();
    $response->assertSessionHasErrors('pin');
});

test('workstation staff list includes all roster operators with pins', function (): void {
    $admin = User::factory()->create(['name' => 'Alex Rivera'])->assignRole(ArkRole::Admin->value);
    $admin->setOperatorPin('1234');

    $molly = User::factory()->create(['name' => 'Molly Soares'])->assignRole(ArkRole::Advisor->value);
    $molly->setOperatorPin('5678');

    $stranger = User::factory()->create(['name' => 'Never Logged In'])->assignRole(ArkRole::Advisor->value);
    $stranger->setOperatorPin('1111');

    $workstation = Workstation::query()->create([
        'shop_settings_id' => ShopSettings::reloadCurrent()->id,
        'name' => 'Front Counter',
        'is_active' => true,
    ]);

    $binding = WorkstationBrowserBinding::issueForWorkstation($workstation);
    $roster = app(WorkstationBrowserRoster::class);
    $roster->remember($binding, $admin);
    $roster->remember($binding, $molly);

    workstationJsonCall($this, 'GET', '/app/api/workstation/staff', $admin, $binding)
        ->assertOk()
        ->assertJsonFragment(['id' => $admin->id, 'name' => 'Alex Rivera'])
        ->assertJsonFragment(['id' => $molly->id, 'name' => 'Molly Soares'])
        ->assertJsonMissing(['id' => $stranger->id]);
});

test('station staff list shows admin when advisor session is active', function (): void {
    $admin = User::factory()->create(['name' => 'Alex Rivera'])->assignRole(ArkRole::Admin->value);
    $admin->setOperatorPin('1234');

    $advisor = User::factory()->create(['name' => 'Morgan Lane'])->assignRole(ArkRole::Advisor->value);
    $advisor->setOperatorPin('5678');

    $workstation = Workstation::query()->create([
        'shop_settings_id' => ShopSettings::reloadCurrent()->id,
        'name' => 'Right',
        'is_active' => true,
    ]);

    $binding = WorkstationBrowserBinding::issueForWorkstation($workstation);
    $roster = app(WorkstationBrowserRoster::class);
    $roster->remember($binding, $admin);
    $roster->remember($binding, $advisor);

    workstationJsonCall($this, 'GET', '/app/api/workstation/staff', $advisor, $binding)
        ->assertOk()
        ->assertJsonFragment(['id' => $admin->id, 'name' => 'Alex Rivera'])
        ->assertJsonFragment(['id' => $advisor->id, 'name' => 'Morgan Lane']);
});

test('bound station shows station label without lock overlay', function (): void {
    $this->withoutVite();

    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
    $admin->setOperatorPin('1234');

    $workstation = Workstation::query()->create([
        'shop_settings_id' => ShopSettings::reloadCurrent()->id,
        'name' => 'Right',
        'is_active' => true,
        'current_operator_user_id' => $admin->id,
    ]);

    TelephonyExtension::query()->create([
        'extension' => '101',
        'display_name' => 'Right Counter',
        'workstation_id' => $workstation->id,
        'device_type' => TelephonyExtensionDeviceType::DeskPhone,
        'enabled' => true,
    ]);

    $binding = WorkstationBrowserBinding::issueForWorkstation($workstation);

    workstationGet($this, '/app', $admin, $binding)
        ->assertOk()
        ->assertSee('ws-presence-topbar__station', false)
        ->assertDontSee('Find advisor', false);
});
