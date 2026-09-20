<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Encounters\EncounterSource;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Operations\Workstations\WorkstationPresence;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

function intakeWorkspaceUrl(array $params = []): string
{
    return route('operations.intake.create', array_merge(['ws' => 'testintake01'], $params));
}

test('advisor intake prefills new customer phone from incoming call link', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $response = $this->get(intakeWorkspaceUrl(['phone' => '7195551234']))
        ->assertOk();

    $html = $response->getContent();

    expect($html)->toMatch('/initialPhone: [\'\"]\(719\) 555-1234[\'\"]/')
        ->and($html)->toContain('initialPhoneFromCall: true')
        ->and(preg_match('/id="intake-customer-first-name"[^>]*autofocus/', $html))->toBe(1)
        ->and(preg_match('/id="intake-customer-phone"[^>]*autofocus/', $html))->toBe(0)
        ->and(preg_match('/id="intake-customer-search"[^>]*autofocus/', $html))->toBe(0);
});

test('advisor intake workspace renders traditional flow sections', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $this->get(intakeWorkspaceUrl())
        ->assertOk()
        ->assertSee('Check In')
        ->assertSee('Recognize customer')
        ->assertSee('Quick check-in')
        ->assertSee('New customer')
        ->assertSee('How they heard about us')
        ->assertSee('Billing class')
        ->assertSee('ops-help-tip', false)
        ->assertSee('Sets default billing for new concerns on this customer.')
        ->assertDontSee('More details (optional)')
        ->assertSee('Address')
        ->assertDontSee('Create RO');

    $customer = Customer::query()->create([
        'first_name' => 'Intake',
        'last_name' => 'Preview',
        'phone' => '555-9090',
    ]);

    Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2018,
        'make' => 'Ford',
        'model' => 'F-150',
    ]);

    $this->withSession([WorkstationPresence::SESSION_BIND_DISMISSED => true])
        ->get(intakeWorkspaceUrl(['customer_id' => $customer->id]))
        ->assertOk()
        ->assertSee('Choose vehicle')
        ->assertSee('Confirm this is the vehicle in the shop today')
        ->assertSee('Use this vehicle')
        ->assertSee('2018 Ford F-150')
        ->assertDontSee('Open Repair Order')
        ->assertDontSee('Reason for visit');

    $this->get(intakeWorkspaceUrl([
        'customer_id' => $customer->id,
        'vehicle_id' => $customer->vehicles()->first()->id,
    ]))
        ->assertOk()
        ->assertSee('Reason for visit')
        ->assertSee('Billing class Retail')
        ->assertSee('Visit &amp; defaults', false)
        ->assertDontSee('Use profile')
        ->assertDontSee('Advisor notes')
        ->assertSee('Change vehicle')
        ->assertSee('Open Repair Order')
        ->assertDontSee('Vehicle issues');
});

test('advisor intake renders vehicle identity pressure without a repair order', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $customer = Customer::query()->create([
        'first_name' => 'No',
        'last_name' => 'Vin',
        'phone' => '555-8181',
    ]);

    Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2017,
        'make' => 'Honda',
        'model' => 'Civic',
        'vin' => null,
    ]);

    $this->withSession([WorkstationPresence::SESSION_BIND_DISMISSED => true])
        ->get(intakeWorkspaceUrl(['customer_id' => $customer->id]))
        ->assertOk()
        ->assertSee('Choose vehicle')
        ->assertSee('2017 Honda Civic')
        ->assertSee('VIN missing', false)
        ->assertDontSee('Why are they here?');
});

test('advisor intake open step preselects shop workflow defaults', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    ShopSettings::current()->update([
        'default_visit_mode' => 'waiting_here',
        'default_recommendation_intent' => 'immediate_attention',
    ]);

    $customer = Customer::query()->create([
        'first_name' => 'Fleet',
        'last_name' => 'Driver',
        'phone' => '555-7070',
        'customer_type' => 'Fleet',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2019,
        'make' => 'Ram',
        'model' => '1500',
    ]);

    $this->get(intakeWorkspaceUrl([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
    ]))
        ->assertOk()
        ->assertSee('name="visit_mode"', false)
        ->assertSee('value="waiting_here"', false)
        ->assertSee('checked', false)
        ->assertSee('name="billing_class"', false)
        ->assertSee('value="Fleet"', false)
        ->assertSee('name="visit_reason"', false)
        ->assertDontSee('defaultRecommendationIntent', false);
});

test('advisor intake open step always offers retail override for warranty customers', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $customer = Customer::query()->create([
        'first_name' => 'Warranty',
        'last_name' => 'Guest',
        'phone' => '555-7171',
        'customer_type' => 'Warranty',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2020,
        'make' => 'Toyota',
        'model' => 'Camry',
    ]);

    $this->get(intakeWorkspaceUrl([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
    ]))
        ->assertOk()
        ->assertSee('value="Retail"', false)
        ->assertSee('value="Warranty"', false)
        ->assertSee('name="visit_reason"', false);
});

test('advisor intake vehicle step offers continue with last vehicle for multi vehicle customers', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $customer = Customer::query()->create([
        'first_name' => 'Single',
        'last_name' => 'Truck',
        'phone' => '555-5151',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2018,
        'make' => 'Ford',
        'model' => 'F-150',
        'plate' => 'F150-18',
    ]);

    $this->get(intakeWorkspaceUrl([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
    ]))
        ->assertOk()
        ->assertSee('Open Repair Order');

    $this->get(intakeWorkspaceUrl([
        'customer_id' => $customer->id,
        'select_vehicle' => 1,
    ]))
        ->assertOk()
        ->assertSee('Choose vehicle')
        ->assertSee('Use this vehicle')
        ->assertSee('2018 Ford F-150')
        ->assertDontSee('Open Repair Order')
        ->assertDontSee('Reason for visit');
});

test('advisor intake vehicle step requires explicit selection for multi vehicle customers', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $customer = Customer::query()->create([
        'first_name' => 'Multi',
        'last_name' => 'Garage',
        'phone' => '555-6060',
    ]);

    $truck = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2018,
        'make' => 'Ford',
        'model' => 'F-150',
        'plate' => 'TRUCK-1',
    ]);

    $sedan = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2020,
        'make' => 'Honda',
        'model' => 'Accord',
        'plate' => 'SEDAN-1',
    ]);

    $this->withSession([WorkstationPresence::SESSION_BIND_DISMISSED => true])
        ->get(intakeWorkspaceUrl(['customer_id' => $customer->id]))
        ->assertOk()
        ->assertSee('2 vehicles on file')
        ->assertSee('Use this vehicle')
        ->assertDontSee('Open Repair Order');

    $this->get(intakeWorkspaceUrl([
        'customer_id' => $customer->id,
        'vehicle_id' => $sedan->id,
    ]))
        ->assertOk()
        ->assertSee('2020 Honda Accord')
        ->assertSee('Open Repair Order');
});

test('advisor intake vehicle step shows search filter for large fleets', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $customer = Customer::query()->create([
        'first_name' => 'Fleet',
        'last_name' => 'Account',
        'phone' => '555-7000',
    ]);

    foreach (range(1, 5) as $index) {
        Vehicle::query()->create([
            'customer_id' => $customer->id,
            'year' => 2015 + $index,
            'make' => 'RAM',
            'model' => '2500',
            'plate' => 'UNIT-'.$index,
            'vin' => '1C6RRFJT'.str_pad((string) $index, 9, '0', STR_PAD_LEFT),
        ]);
    }

    $this->withSession([WorkstationPresence::SESSION_BIND_DISMISSED => true])
        ->get(intakeWorkspaceUrl(['customer_id' => $customer->id]))
        ->assertOk()
        ->assertSee('5 vehicles on file')
        ->assertSee('Find vehicle')
        ->assertSee('Year, make, model, plate, or VIN')
        ->assertSee('arkIntakeVehicleSelect', false);
});

test('advisor intake vehicle step links active repair order for workspace open', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $customer = Customer::query()->create([
        'first_name' => 'Active',
        'last_name' => 'Ro',
        'phone' => '555-7070',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2016,
        'make' => 'RAM',
        'model' => '2500',
        'trim' => 'Laramie',
    ]);

    Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2019,
        'make' => 'Jeep',
        'model' => 'Wrangler',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Draft,
        'concern_summary' => 'Customer reports noise.',
    ]);

    $this->withSession([WorkstationPresence::SESSION_BIND_DISMISSED => true])
        ->get(intakeWorkspaceUrl(['customer_id' => $customer->id]))
        ->assertOk()
        ->assertSee('RO #'.$repairOrder->repair_order_id)
        ->assertSee(route('operations.repair-orders.show', $repairOrder), false)
        ->assertSee('ops-ro-footnote-link', false)
        ->assertDontSee('ops-ro-footnote-link" target="_blank"', false)
        ->assertSee('Draft');
});

test('advisor intake opens repair order with visit reason and empty estimate', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    ShopSettings::current()->update(['learn_training_gate_enabled' => false]);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $this->actingAs($advisor);

    $customer = Customer::query()->create([
        'first_name' => 'Rosa',
        'last_name' => 'Garcia',
        'phone' => '555-0100',
        'customer_type' => 'Retail',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2016,
        'make' => 'Chevrolet',
        'model' => 'Tahoe',
        'plate' => 'TAH-2016',
    ]);

    $visitReason = "My brakes make noise on hard stops.\nI think I need front and rear brakes.";

    $response = $this->post(route('operations.intake.store'), [
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'visit_reason' => $visitReason,
        'visit_mode' => 'drop_off',
        'billing_class' => 'Warranty',
    ]);

    $repairOrder = RepairOrder::query()->sole();

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('operations.repair-orders.show', $repairOrder).'#visit-reason')
        ->assertSessionHas('status');

    expect($repairOrder)
        ->customer_id->toBe($customer->id)
        ->vehicle_id->toBe($vehicle->id)
        ->encounter_id->toBeNull()
        ->status->is(RepairOrderStatus::Draft)->toBeTrue()
        ->visit_reason->toBe($visitReason)
        ->concern_summary->toBe('')
        ->drop_off->toBeTrue()
        ->warranty->toBeTrue()
        ->and($repairOrder->concerns)->toHaveCount(0);

    expect($customer->fresh()->customer_type)->toBe('Warranty');
});

test('advisor intake auto-assigns the sole staff member as technician', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    ShopSettings::current()->update(['learn_training_gate_enabled' => false]);
    $solo = User::factory()->create()->assignRole(ArkRole::Admin->value);
    $this->actingAs($solo);

    $customer = Customer::query()->create([
        'first_name' => 'Solo',
        'last_name' => 'Owner',
        'phone' => '555-0606',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2018,
        'make' => 'Ford',
        'model' => 'Transit',
    ]);

    $this->post(route('operations.intake.store'), [
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'visit_mode' => 'drop_off',
    ])->assertSessionHasNoErrors();

    expect(RepairOrder::query()->sole()->assigned_technician_id)->toBe($solo->id);
});

test('advisor intake does not auto-assign a technician when more than one staff member exists', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    ShopSettings::current()->update(['learn_training_gate_enabled' => false]);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    User::factory()->create()->assignRole(ArkRole::Technician->value);
    $this->actingAs($advisor);

    $customer = Customer::query()->create([
        'first_name' => 'Team',
        'last_name' => 'Shop',
        'phone' => '555-0707',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2019,
        'make' => 'Chevrolet',
        'model' => 'Express',
    ]);

    $this->post(route('operations.intake.store'), [
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'visit_mode' => 'drop_off',
    ])->assertSessionHasNoErrors();

    expect(RepairOrder::query()->sole()->assigned_technician_id)->toBeNull();
});

test('advisor intake vehicle lookup resolves vin to customer and vehicle', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $customer = Customer::query()->create([
        'first_name' => 'Vin',
        'last_name' => 'Lookup',
        'phone' => '555-1313',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2019,
        'make' => 'Honda',
        'model' => 'Accord',
        'vin' => '1HGCM82633A004352',
        'normalized_vin' => '1HGCM82633A004352',
        'plate' => 'VIN-1313',
    ]);

    $this->getJson(route('operations.intake.vehicles.lookup', ['q' => '1HGCM82633A004352']))
        ->assertOk()
        ->assertJsonPath('customer_id', $customer->id)
        ->assertJsonPath('vehicle_id', $vehicle->id)
        ->assertJsonPath('customer_name', 'Vin Lookup')
        ->assertJsonPath('vehicle_name', $vehicle->display_name);

    $this->getJson(route('operations.intake.vehicles.lookup', ['q' => 'VIN-1313']))
        ->assertOk()
        ->assertJsonPath('vehicle_id', $vehicle->id);

    $this->getJson(route('operations.intake.vehicles.lookup', ['q' => 'UNKNOWN-VIN-999']))
        ->assertNotFound()
        ->assertJsonPath('message', 'No vehicle on file for that VIN. Add the customer and vehicle below.');
});

test('advisor intake vehicle lookup deep links into open ro step', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $customer = Customer::query()->create([
        'first_name' => 'Lane',
        'last_name' => 'Checkin',
        'phone' => '555-1414',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2022,
        'make' => 'Ford',
        'model' => 'Bronco',
        'vin' => '1FMEE5DP9PLB12345',
        'normalized_vin' => '1FMEE5DP9PLB12345',
    ]);

    $this->get(intakeWorkspaceUrl([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
    ]))
        ->assertOk()
        ->assertSee('Lane Checkin')
        ->assertSee('2022 Ford Bronco')
        ->assertSee('Open Repair Order');
});

test('advisor intake can open repair order without visit reason and land on reason for visit', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $this->actingAs($advisor);

    $customer = Customer::query()->create([
        'first_name' => 'Quick',
        'last_name' => 'Open',
        'phone' => '555-0202',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2020,
        'make' => 'Toyota',
        'model' => 'RAV4',
    ]);

    $response = $this->post(route('operations.intake.store'), [
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'visit_mode' => 'waiting_here',
    ]);

    $repairOrder = RepairOrder::query()->sole();

    $response
        ->assertRedirect(route('operations.repair-orders.show', $repairOrder).'#visit-reason')
        ->assertSessionHas('status');

    expect($repairOrder->concerns)->toHaveCount(0)
        ->and($repairOrder->concern_summary)->toBe('')
        ->and($repairOrder->visit_reason)->toBeNull();
});

test('advisor intake requires visit mode before opening repair order', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $customer = Customer::query()->create([
        'first_name' => 'Visit',
        'last_name' => 'Required',
        'phone' => '555-0303',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2019,
        'make' => 'Honda',
        'model' => 'Civic',
    ]);

    $this->from(intakeWorkspaceUrl(['customer_id' => $customer->id, 'vehicle_id' => $vehicle->id]))
        ->post(route('operations.intake.store'), [
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
        ])
        ->assertSessionHasErrors('visit_mode');

    expect(RepairOrder::query()->count())->toBe(0);
});

test('advisor intake requires phone when creating a customer inline', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $this->from(intakeWorkspaceUrl())
        ->post(route('operations.customers.store'), [
            'intake' => '1',
            'first_name' => 'No',
            'last_name' => 'Phone',
        ])
        ->assertSessionHasErrors('phone');
});

test('advisor intake can create a new customer inline', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $this->post(route('operations.customers.store'), [
        'intake' => '1',
        'first_name' => 'New',
        'last_name' => 'Caller',
        'phone' => '555-7777',
        'customer_type' => 'Retail',
    ])->assertRedirect();

    $customer = Customer::query()->where('phone', '5557777')->firstOrFail();

    $this->withSession([WorkstationPresence::SESSION_BIND_DISMISSED => true])
        ->get(intakeWorkspaceUrl(['customer_id' => $customer->id]))
        ->assertOk()
        ->assertSee('New Caller')
        ->assertSee('Add vehicle to continue')
        ->assertDontSee('Open Repair Order');
});

test('advisor intake can create a vehicle inline', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $customer = Customer::query()->create([
        'first_name' => 'Van',
        'last_name' => 'Owner',
        'phone' => '555-8888',
    ]);

    $response = $this->post(route('operations.customers.vehicles.store', $customer), [
        'intake' => '1',
        'ws' => 'testintake01',
        'year' => 2016,
        'make' => 'Chevrolet',
        'model' => 'Tahoe',
        'plate' => 'NEW-2016',
    ]);

    $vehicle = $customer->vehicles()->firstOrFail();

    $response->assertRedirect(intakeWorkspaceUrl([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
    ]));

    $this->get(intakeWorkspaceUrl([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
    ]))
        ->assertOk()
        ->assertSee('2016 Chevrolet Tahoe')
        ->assertSee('Open Repair Order');
});

test('advisor intake duplicate watch finds existing customers by phone email and name', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $customer = Customer::query()->create([
        'first_name' => 'Edward',
        'last_name' => 'Soares',
        'phone' => '7195550199',
        'email' => 'edward@example.com',
    ]);

    $this->get(route('operations.intake.customers.duplicates', [
        'phone' => '7195550199',
    ]), [
        'X-Requested-With' => 'XMLHttpRequest',
    ])
        ->assertOk()
        ->assertSee('Similar customers on file')
        ->assertSee('Possible matches while you')
        ->assertSee('Use one below if it')
        ->assertSee('Edward Soares')
        ->assertSee('Phone')
        ->assertSee('Use this customer')
        ->assertSee('data-intake-customer-id="'.$customer->id.'"', false);

    $this->get(route('operations.intake.customers.duplicates', [
        'email' => 'edward@example.com',
    ]), [
        'X-Requested-With' => 'XMLHttpRequest',
    ])
        ->assertOk()
        ->assertSee('Email');

    $this->get(route('operations.intake.customers.duplicates', [
        'first_name' => 'Edward',
        'last_name' => 'Soares',
    ]), [
        'X-Requested-With' => 'XMLHttpRequest',
    ])
        ->assertOk()
        ->assertSee('Name');
});

test('advisor intake can create a customer with address and referral source', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $this->post(route('operations.customers.store'), [
        'intake' => '1',
        'first_name' => 'Jordan',
        'last_name' => 'Price',
        'phone' => '719-555-1212',
        'address_line_1' => '123 Main St',
        'address_line_2' => 'Unit 4B',
        'city' => 'Demo City',
        'state' => 'CO',
        'postal_code' => '80903',
        'referral_source' => EncounterSource::Website->value,
        'customer_type' => 'Retail',
    ])->assertRedirect();

    $customer = Customer::query()->where('phone', '7195551212')->firstOrFail();

    expect($customer)
        ->phone->toBe('7195551212')
        ->display_phone->toBe('(719) 555-1212')
        ->address_line_1->toBe('123 Main St')
        ->address_line_2->toBe('Unit 4B')
        ->city->toBe('Demo City')
        ->state->toBe('CO')
        ->postal_code->toBe('80903')
        ->referral_source->toBe(EncounterSource::Website->value);
});

test('advisor intake customer prefill endpoint returns customer fields', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $customer = Customer::query()->create([
        'first_name' => 'Prefill',
        'last_name' => 'Target',
        'phone' => '7195559090',
        'email' => 'prefill@example.com',
        'address_line_1' => '100 Main St',
        'city' => 'Denver',
        'state' => 'CO',
        'postal_code' => '80202',
        'customer_type' => 'Retail',
    ]);

    $this->getJson(route('operations.intake.customers.show', $customer))
        ->assertOk()
        ->assertJsonPath('id', $customer->id)
        ->assertJsonPath('first_name', 'Prefill')
        ->assertJsonPath('address_line_1', '100 Main St')
        ->assertJsonPath('city', 'Denver');
});

test('advisor intake can update selected customer and continue', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $customer = Customer::query()->create([
        'first_name' => 'Needs',
        'last_name' => 'Update',
        'phone' => '7195553030',
        'customer_type' => 'Retail',
    ]);

    $this->from(intakeWorkspaceUrl())
        ->patch(route('operations.customers.update', $customer), [
            'intake' => '1',
            'ws' => 'testintake01',
            'first_name' => 'Needs',
            'last_name' => 'Update',
            'phone' => '7195553030',
            'email' => 'updated@example.com',
            'address_line_1' => '500 Updated Ave',
            'city' => 'Demo City',
            'state' => 'CO',
            'postal_code' => '80909',
            'customer_type' => 'Retail',
        ])
        ->assertRedirect(intakeWorkspaceUrl(['customer_id' => $customer->id]));

    expect($customer->fresh())
        ->email->toBe('updated@example.com')
        ->address_line_1->toBe('500 Updated Ave');
});

test('advisor intake live customer search returns matching results', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $advisor = actingAsLearnCurrentAdvisor();
    $this->actingAs($advisor);

    $customer = Customer::query()->create([
        'first_name' => 'Nora',
        'last_name' => 'Ellis',
        'phone' => '555-8181',
    ]);

    Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2019,
        'make' => 'Toyota',
        'model' => 'Camry',
    ]);

    $customer->update([
        'email' => 'nora@example.com',
        'address_line_1' => '100 Main St',
        'city' => 'Denver',
        'state' => 'CO',
        'postal_code' => '80202',
    ]);

    $this->get(route('operations.intake.customers.search', ['q' => '555-8181']), [
        'X-Requested-With' => 'XMLHttpRequest',
    ])
        ->assertOk()
        ->assertSee('Nora Ellis')
        ->assertSee('nora@example.com')
        ->assertSee('100 Main St')
        ->assertSee('Denver, CO 80202')
        ->assertSee('1 vehicle')
        ->assertSee('no active RO')
        ->assertDontSee('Toyota')
        ->assertSee('data-intake-customer-id="'.$customer->id.'"', false);
});

test('advisor intake live customer search matches first and last name together', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $customer = Customer::query()->create([
        'first_name' => 'Edward',
        'last_name' => 'Soares',
        'phone' => '555-6414',
    ]);

    $this->get(route('operations.intake.customers.search', ['q' => 'edward soares']), [
        'X-Requested-With' => 'XMLHttpRequest',
    ])
        ->assertOk()
        ->assertSee('Edward Soares')
        ->assertSee('data-intake-customer-id="'.$customer->id.'"', false);

    $this->get(route('operations.intake.customers.search', ['q' => 'soares edward']), [
        'X-Requested-With' => 'XMLHttpRequest',
    ])
        ->assertOk()
        ->assertSee('Edward Soares');

    $this->get(route('operations.customers.search', ['q' => 'edward soares']))
        ->assertOk()
        ->assertSee('Edward Soares');
});

test('advisor intake live customer search shows active repair order meta', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $customer = Customer::query()->create([
        'first_name' => 'Riley',
        'last_name' => 'Grant',
        'phone' => '555-9090',
        'email' => 'riley@example.com',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2021,
        'make' => 'Subaru',
        'model' => 'Outback',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::WaitingApproval,
        'concern_summary' => 'Customer reports brake noise.',
    ]);

    $this->get(route('operations.intake.customers.search', ['q' => '555-9090']), [
        'X-Requested-With' => 'XMLHttpRequest',
    ])
        ->assertOk()
        ->assertSee('Riley Grant')
        ->assertSee('Waiting Approval')
        ->assertSee('RO #'.$repairOrder->repair_order_id)
        ->assertSee(route('operations.repair-orders.show', $repairOrder), false)
        ->assertSee('ops-ro-footnote-link', false)
        ->assertDontSee('ops-ro-footnote-link" target="_blank"', false)
        ->assertDontSee('no active RO');
});

test('customer search intake mode links into advisor intake', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $advisor = actingAsLearnCurrentAdvisor();
    $this->actingAs($advisor);

    $customer = Customer::query()->create([
        'first_name' => 'Mia',
        'last_name' => 'Lopez',
        'phone' => '555-4242',
    ]);

    Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2020,
        'make' => 'Honda',
        'model' => 'Civic',
    ]);

    $this->get(route('operations.customers.search', ['q' => '555-4242', 'intake' => 1]))
        ->assertOk()
        ->assertSee(route('operations.intake.create', ['customer_id' => $customer->id]), false);
});
