<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Http;

test('the shop can move from customer search to vehicle to repair order draft', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(actingAsLearnCurrentAdvisor());

    $customer = Customer::query()->create([
        'first_name' => 'Rosa',
        'last_name' => 'Garcia',
        'phone' => '555-0100',
        'email' => 'rosa@example.com',
    ]);

    $this->get(route('operations.customers.search', ['q' => '555-0100']))
        ->assertOk()
        ->assertSee('Rosa Garcia');

    $this->post(route('operations.customers.vehicles.store', $customer), [
        'vin' => '1HGCM82633A004352',
        'plate' => 'ARK123',
        'year' => 2018,
        'make' => 'Honda',
        'model' => 'Accord',
        'trim' => 'EX',
        'engine' => '2.0L',
        'drive' => 'FWD',
        'transmission' => 'Automatic',
        'plate_state' => 'CO',
    ])->assertRedirect(route('operations.customers.show', $customer));

    $vehicle = Vehicle::query()->where('plate', 'ARK123')->firstOrFail();

    $this->get(route('operations.customers.show', $customer))
        ->assertOk()
        ->assertSee('Start RO')
        ->assertSee('activeRoVehicleId', false)
        ->assertSee('aria-controls="vehicle-ro-create-'.$vehicle->id.'"', false)
        ->assertSee('x-show="activeRoVehicleId === '.$vehicle->id.'"', false)
        ->assertSee('Create Estimate / RO')
        ->assertSee('ops-vin-display', false)
        ->assertSee('3A004352')
        ->assertDontSee('Customer concern for this', false)
        ->assertDontSee('Create RO Draft');

    $response = $this->post(route('operations.customers.repair-orders.drafts.store', $customer), [
        'vehicle_id' => $vehicle->id,
        'visit_reason' => 'Customer states engine rattles on cold start.',
    ]);

    $repairOrder = RepairOrder::query()->firstOrFail();

    $response->assertRedirect(route('operations.repair-orders.show', $repairOrder));

    expect($repairOrder->customer_id)->toBe($customer->id)
        ->and($repairOrder->vehicle_id)->toBe($vehicle->id)
        ->and($repairOrder->status->is(RepairOrderStatus::Draft))->toBeTrue()
        ->and($repairOrder->visit_reason)->toBe('Customer states engine rattles on cold start.')
        ->and(RepairOrderConcern::query()->where('repair_order_id', $repairOrder->id)->count())->toBe(0);

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Reason for Visit')
        ->assertSee('Customer states engine rattles on cold start.')
        ->assertDontSee('Toggle Mode (V)', false)
        ->assertDontSee('>Editing<', false)
        ->assertSee('+ Add Work', false);

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Customer states engine rattles on cold start.');
});

test('the shop can edit customer and vehicle details from the customer page', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(actingAsLearnCurrentAdvisor());

    $customer = Customer::query()->create([
        'first_name' => 'Robert',
        'last_name' => 'Kim',
        'phone' => '7195550113',
        'email' => 'robert@example.test',
        'customer_type' => 'Retail',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'vin' => '1GNSKCE07CR040404',
        'plate' => 'MLT404',
        'year' => 2012,
        'make' => 'Chevrolet',
        'model' => 'Tahoe',
        'trim' => 'LT',
        'engine' => '5.3L V8',
        'drive' => '4WD/4-Wheel Drive/4x4',
        'transmission' => 'Automatic',
        'plate_state' => 'CO',
    ]);

    $this->get(route('operations.customers.show', $customer))
        ->assertOk()
        ->assertSee('Save Customer')
        ->assertSee('Save Vehicle');

    $this->patch(route('operations.customers.update', $customer), [
        'first_name' => 'Roberto',
        'last_name' => 'Kim',
        'phone' => '7195550199',
        'email' => 'roberto@example.test',
        'customer_type' => 'Military',
        'notes' => 'Prefers text updates.',
    ])->assertRedirect(route('operations.customers.show', $customer));

    $this->patch(route('operations.customers.vehicles.update', [$customer, $vehicle]), [
        'vin' => '1GNSKCE07CR040404',
        'plate' => 'SAFE404',
        'plate_state' => 'CO',
        'year' => 2012,
        'make' => 'Chevrolet',
        'model' => 'Tahoe LT',
        'trim' => 'Premier',
        'engine' => '5.3L V8',
        'drive' => '4WD/4-Wheel Drive/4x4',
        'transmission' => 'Automatic',
        'color' => 'Black',
        'nickname' => 'Safety Tahoe',
        'public_notes' => 'Check child seats before road test.',
        'private_notes' => 'Customer prefers OEM brake parts.',
    ])->assertRedirect(route('operations.customers.show', $customer));

    expect($customer->fresh())
        ->first_name->toBe('Roberto')
        ->phone->toBe('7195550199')
        ->customer_type->toBe('Military')
        ->and($vehicle->fresh())
        ->plate->toBe('SAFE404')
        ->model->toBe('Tahoe LT')
        ->trim->toBe('Premier')
        ->normalized_vin->toBe('1GNSKCE07CR040404')
        ->drivetrain->toBe('4wd')
        ->transmission->toBe('Automatic')
        ->normalized_vehicle_key->toBe('2012-chevrolet-tahoe-lt-premier-5-3l-4wd-automatic')
        ->vehicle_identity_source->toBe('manual')
        ->nickname->toBe('Safety Tahoe');
});

test('vehicle store normalizes all caps make model and trim on save', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(actingAsLearnCurrentAdvisor());

    $customer = Customer::query()->create([
        'first_name' => 'Lane',
        'last_name' => 'Ford',
        'phone' => '555-0199',
    ]);

    $this->post(route('operations.customers.vehicles.store', $customer), [
        'vin' => '1FA6P8TH4J5123456',
        'year' => 2018,
        'make' => 'FORD',
        'model' => 'MUSTANG',
        'trim' => 'GT',
    ])->assertRedirect(route('operations.customers.show', $customer));

    expect(Vehicle::query()->firstOrFail())
        ->make->toBe('Ford')
        ->model->toBe('Mustang')
        ->trim->toBe('GT');
});

test('vin decode normalizes all caps make model and trim from nhtsa', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(actingAsLearnCurrentAdvisor());

    config()->set('services.partstech.username', null);
    config()->set('services.partstech.api_key', null);

    Http::fake([
        'vpic.nhtsa.dot.gov/*' => Http::response([
            'Results' => [[
                'ModelYear' => '2018',
                'Make' => 'FORD',
                'Model' => 'MUSTANG',
                'Trim' => 'GT',
                'EngineModel' => '5.0L',
                'DriveType' => 'Rear-Wheel Drive',
                'TransmissionStyle' => 'Automatic',
            ]],
        ]),
    ]);

    $this->postJson(route('operations.vehicles.decode-vin'), [
        'vin' => '1FA6P8TH4J5123456',
    ])
        ->assertOk()
        ->assertJsonPath('make', 'Ford')
        ->assertJsonPath('model', 'Mustang')
        ->assertJsonPath('trim', 'GT');
});

test('the shop can decode a vin and still override fields before saving', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(actingAsLearnCurrentAdvisor());

    config()->set('services.partstech.username', null);
    config()->set('services.partstech.api_key', null);

    Http::fake([
        'vpic.nhtsa.dot.gov/*' => Http::response([
            'Results' => [[
                'ModelYear' => '2019',
                'Make' => 'Toyota',
                'Model' => 'RAV4',
                'Trim' => 'XLE',
                'EngineModel' => '2.5L',
                'DriveType' => 'All-Wheel Drive',
                'TransmissionStyle' => 'Automatic',
                'FuelTypePrimary' => 'Gasoline',
                'BodyClass' => 'Sport Utility Vehicle',
            ]],
        ]),
    ]);

    $response = $this->postJson(route('operations.vehicles.decode-vin'), [
        'vin' => '2T3RFREV6KW020202',
    ]);

    $response->assertOk()
        ->assertJsonPath('year', 2019)
        ->assertJsonPath('make', 'Toyota')
        ->assertJsonPath('model', 'Rav4')
        ->assertJsonPath('trim', 'XLE')
        ->assertJsonPath('drive', 'AWD')
        ->assertJsonPath('drivetrain', 'awd')
        ->assertJsonPath('transmission', 'Automatic')
        ->assertJsonPath('fuel_type', 'gasoline')
        ->assertJsonPath('body_style', 'Sport Utility Vehicle')
        ->assertJsonPath('normalized_vehicle_key', '2019-toyota-rav4-xle-2-5l-awd-automatic');
});

test('plate decode is not available in stock core', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(actingAsLearnCurrentAdvisor());

    Http::fake();

    $this->postJson(route('operations.vehicles.decode-vin'), [
        'plate' => 'ABC123',
        'plate_state' => 'CO',
    ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Vehicle could not be decoded from that plate. Plate decode is not available.');

    Http::assertNothingSent();
});
