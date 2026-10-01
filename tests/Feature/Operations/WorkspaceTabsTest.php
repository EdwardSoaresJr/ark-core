<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Operations\Workspace\WorkspaceTabSupport;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Http\Request;

test('detects repair order show path', function () {
    $request = Request::create('/app/repair-orders/1024', 'GET');

    $entity = WorkspaceTabSupport::detectFromRequest($request);

    expect($entity)->not->toBeNull()
        ->and($entity['entityType'])->toBe('repair_order')
        ->and($entity['entityId'])->toBe('1024')
        ->and($entity['key'])->toBe('repair_order:1024')
        ->and($entity['route'])->toBe('/app/repair-orders/1024');
});

test('repair order workspace route uses canonical show path', function () {
    $request = Request::create('/app/repair-orders/1024/edit?editing_line=9', 'GET');

    expect(WorkspaceTabSupport::repairOrderWorkspaceRoute('1024', $request))
        ->toBe('/app/repair-orders/1024');
});

test('detects repair order edit path as same entity key with show route', function () {
    $request = Request::create('/app/repair-orders/1024/edit?editing_line=55', 'GET');

    $entity = WorkspaceTabSupport::detectFromRequest($request);

    expect($entity)->not->toBeNull()
        ->and($entity['key'])->toBe('repair_order:1024')
        ->and($entity['route'])->toBe('/app/repair-orders/1024');
});

test('detects customer show path', function () {
    $request = Request::create('/app/customers/55', 'GET');

    $entity = WorkspaceTabSupport::detectFromRequest($request);

    expect($entity)->not->toBeNull()
        ->and($entity['entityType'])->toBe('customer')
        ->and($entity['key'])->toBe('customer:55');
});

test('detects vehicle focus via customer query param', function () {
    $request = Request::create('/app/customers/55?vehicle=9', 'GET');

    $entity = WorkspaceTabSupport::detectFromRequest($request);

    expect($entity)->not->toBeNull()
        ->and($entity['entityType'])->toBe('vehicle')
        ->and($entity['key'])->toBe('vehicle:9');
});

test('detects service intake workspace with query context', function () {
    $customer = Customer::query()->create([
        'first_name' => 'Maria',
        'last_name' => 'Lopez',
        'phone' => '555-0100',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2018,
        'make' => 'Ford',
        'model' => 'F-150',
    ]);

    $request = Request::create('/app/intake/new?ws=abc123456789&customer_id='.$customer->id.'&vehicle_id='.$vehicle->id, 'GET');

    $entity = WorkspaceTabSupport::detectFromRequest($request);

    expect($entity)->not->toBeNull()
        ->and($entity['entityType'])->toBe('intake')
        ->and($entity['key'])->toBe('intake:abc123456789')
        ->and($entity['route'])->toContain('/app/intake/new')
        ->and($entity['route'])->toContain('ws=abc123456789')
        ->and($entity['route'])->toContain('customer_id='.$customer->id)
        ->and($entity['route'])->toContain('vehicle_id='.$vehicle->id)
        ->and($entity['title'])->toBe('Maria Lopez');
});

test('intake workspace title uses customer name when recognized', function () {
    $customer = Customer::query()->create([
        'first_name' => 'Ben',
        'last_name' => 'Trainee',
        'phone' => '555-0101',
    ]);

    $request = Request::create('/app/intake/new?ws=abc123456789&customer_id='.$customer->id, 'GET');

    expect(WorkspaceTabSupport::intakeTabTitle($request))->toBe('Ben Trainee');
});

test('intake workspace subtitle reflects customer and vehicle', function () {
    $customer = Customer::query()->create([
        'first_name' => 'Maria',
        'last_name' => 'Lopez',
        'phone' => '555-0100',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2018,
        'make' => 'Ford',
        'model' => 'F-150',
    ]);

    $request = Request::create('/app/intake/new?ws=abc123456789&customer_id='.$customer->id.'&vehicle_id='.$vehicle->id, 'GET');

    $boot = WorkspaceTabSupport::enrichBoot(
        WorkspaceTabSupport::buildIntakePayload($request),
        $request,
    );

    expect($boot['title'])->toBe('Maria Lopez')
        ->and($boot['subtitle'])->toBe('Maria Lopez · 2018 Ford F-150');
});

test('intake qualification index is not a workspace tab', function () {
    $request = Request::create('/app/intake', 'GET');

    expect(WorkspaceTabSupport::detectFromRequest($request))->toBeNull();
});

test('bare intake redirects to assign a workspace id', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $user = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $response = $this->actingAs($user)
        ->get(route('operations.intake.create'));

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toMatch('#/app/intake/new\?ws=[a-z0-9]{12}$#');
});

test('operations layout boots workspace tabs for service intake', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $user = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $this->actingAs($user)
        ->followingRedirects()
        ->get(route('operations.intake.create'))
        ->assertOk()
        ->assertSee('id="ops-workspace-tabs"', false)
        ->assertSee('intake:service', false)
        ->assertSee('Check In', false);
});

test('operations report path is not a workspace tab', function () {
    $request = Request::create('/app/reports/operations?tab=financial', 'GET');

    expect(WorkspaceTabSupport::detectFromRequest($request))->toBeNull();
});

test('client config excludes operations report from permanent dock tabs', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $config = WorkspaceTabSupport::clientConfig();

    expect($config['permanentPinned'])->toBe([])
        ->and($config['excludedWorkspaceKeys'])->toContain('report:operations')
        ->and($config['dockedContextual'])->toBeArray()
        ->and($config['dockedContextual'][0]['key'])->toBe('intake:service')
        ->and($config['dockedContextual'][0]['route'])->toBe('/app/intake/new')
        ->and($config['dockedContextual'][0]['title'])->toBe('Check In');
});

test('operations layout boots workspace tabs for repair order show', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $user = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    [$repairOrder] = identityHeaderRepairOrderFixture();

    $this->actingAs($user)
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('id="ops-workspace-tabs"', false)
        ->assertSee('window.__ARK_WORKSPACE__', false)
        ->assertSee('repair_order:'.$repairOrder->repair_order_id, false)
        ->assertSee('Amber A.', false)
        ->assertSee('2011 Acura ZDX', false)
        ->assertDontSee('ops-workspace-limit-modal', false);
});

test('repair order workspace boot uses short customer and vehicle labels', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    [$repairOrder] = identityHeaderRepairOrderFixture();

    $boot = WorkspaceTabSupport::bootFromRepairOrder($repairOrder, Request::create('/app/repair-orders/'.$repairOrder->repair_order_id, 'GET'));

    expect($boot['title'])->toBe($repairOrder->repair_order_id.' · Amber A.')
        ->and($boot['customerName'])->toBe('Amber A.')
        ->and($boot['subtitle'])->toBe('2011 Acura ZDX')
        ->and($boot['route'])->toBe('/app/repair-orders/'.$repairOrder->repair_order_id);
});

test('workspace tab client config exposes open tab cap', function () {
    config(['ark_workspace_tabs.max_tabs' => 10, 'ark_workspace_tabs.tab_min_width' => 176]);

    $config = WorkspaceTabSupport::clientConfig();

    expect($config['maxTabs'])->toBe(10)
        ->and($config['tabMinWidth'])->toBe(176);
});

test('operations layout boots workspace tabs for customer show', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $user = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $customer = Customer::query()->create([
        'first_name' => 'John',
        'last_name' => 'Smith',
        'phone' => '555-0100',
    ]);

    $this->actingAs($user)
        ->get(route('operations.customers.show', $customer))
        ->assertOk()
        ->assertSee('id="ops-workspace-tabs"', false)
        ->assertSee('customer:'.$customer->id, false)
        ->assertSee('John Smith', false);
});

test('operations layout enriches vehicle workspace boot metadata', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $user = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $customer = Customer::query()->create([
        'first_name' => 'Vehicle',
        'last_name' => 'Owner',
        'phone' => '555-0101',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2018,
        'make' => 'Ford',
        'model' => 'F-150',
    ]);

    $boot = WorkspaceTabSupport::enrichBoot(
        WorkspaceTabSupport::buildVehiclePayload((string) $vehicle->id, Request::create(
            '/app/customers/'.$customer->id.'?vehicle='.$vehicle->id,
            'GET',
        )),
    );

    expect($boot['title'])->toBe('2018 Ford F-150')
        ->and($boot['subtitle'])->toBe('Vehicle Owner')
        ->and($boot['route'])->toContain('vehicle='.$vehicle->id);
});

test('workspace tabs can be disabled via config', function () {
    config(['ark_workspace_tabs.enabled' => false]);

    $this->seed(ArkAuthorizationSeeder::class);
    $user = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $customer = Customer::query()->create([
        'first_name' => 'Disabled',
        'last_name' => 'Tabs',
        'phone' => '555-0102',
    ]);

    $this->actingAs($user)
        ->get(route('operations.customers.show', $customer))
        ->assertOk()
        ->assertDontSee('id="ops-workspace-tabs"', false);
});

test('client config closes intake workspace after successful open ro', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $user = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $customer = Customer::query()->create([
        'first_name' => 'Reset',
        'last_name' => 'Intake',
        'phone' => '555-0199',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2020,
        'make' => 'Toyota',
        'model' => 'Camry',
    ]);

    $this->actingAs($user)
        ->post(route('operations.intake.store'), [
            'ws' => 'abc123456789',
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'visit_mode' => 'drop_off',
            'concerns' => [
                ['customer_states' => 'Brake noise'],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHas('workspace_close_intake_ws', 'abc123456789');

    expect(WorkspaceTabSupport::clientConfig()['closeIntakeWorkspaceId'])->toBe('abc123456789')
        ->and(WorkspaceTabSupport::clientConfig()['closeIntakeWorkspaceId'])->toBeNull();
});

test('client config includes per user storage key', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $user = User::factory()->create();

    $this->actingAs($user);

    $config = WorkspaceTabSupport::clientConfig();

    expect($config['enabled'])->toBeTrue()
        ->and($config['storageKey'])->toBe('ark_ws_v2_'.$user->id)
        ->and($config['dashboardUrl'])->toContain('/app');
});
