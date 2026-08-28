<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
});

test('customer index loads customers without a search query', function () {
    Customer::query()->create([
        'first_name' => 'Idle',
        'last_name' => 'Browse',
        'phone' => '555-9999',
    ]);

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->get(route('operations.customers.search'))
        ->assertOk()
        ->assertSee('Idle Browse')
        ->assertSee('Customers', false)
        ->assertSee('Any type', false)
        ->assertSee('From', false)
        ->assertDontSee('Start typing to find a customer.');
});

test('customer search finds by phone and links to hub', function () {
    $customer = Customer::query()->create([
        'first_name' => 'Rosa',
        'last_name' => 'Garcia',
        'phone' => '555-0100',
    ]);

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->get(route('operations.customers.search', ['q' => '555-0100']))
        ->assertOk()
        ->assertSee('Rosa Garcia')
        ->assertSee('Customers', false)
        ->assertSee(route('operations.vehicles.search'), false);
});

test('customer index filters by type and created date', function () {
    Customer::query()->create([
        'first_name' => 'Retail',
        'last_name' => 'Alpha',
        'phone' => '555-1111',
        'customer_type' => 'Retail',
        'created_at' => now()->subDays(10),
    ]);
    Customer::query()->create([
        'first_name' => 'Fleet',
        'last_name' => 'Beta',
        'phone' => '555-2222',
        'customer_type' => 'Fleet',
        'created_at' => now()->subDay(),
    ]);

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->get(route('operations.customers.search', [
            'type' => 'Fleet',
            'created_from' => now()->subDays(3)->toDateString(),
        ]))
        ->assertOk()
        ->assertSee('Fleet Beta')
        ->assertDontSee('Retail Alpha')
        ->assertSee('Clear', false);
});

test('vehicle search finds by plate and opens customer hub on vehicle', function () {
    $customer = Customer::query()->create([
        'first_name' => 'Hunter',
        'last_name' => 'Bell',
        'phone' => '555-4242',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2019,
        'make' => 'Ford',
        'model' => 'F-150',
        'plate' => 'ARK123',
        'plate_state' => 'CO',
        'vin' => '1FTFW1E84MKD12345',
    ]);

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->get(route('operations.vehicles.search'))
        ->assertOk()
        ->assertSee('2019 Ford F-150')
        ->assertSee('Any work', false)
        ->assertDontSee('Start typing to find a vehicle.');

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->get(route('operations.vehicles.search', ['q' => 'ARK123']))
        ->assertOk()
        ->assertSee('2019 Ford F-150')
        ->assertSee('Hunter Bell')
        ->assertSee(route('operations.customers.show', ['customer' => $customer, 'vehicle' => $vehicle->id]), false);
});

test('vehicle index filters open work', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $customer = Customer::query()->create([
        'first_name' => 'Open',
        'last_name' => 'Work',
        'phone' => '555-3333',
    ]);
    $openVehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2021,
        'make' => 'Chevy',
        'model' => 'Silverado',
        'plate' => 'OPEN1',
    ]);
    Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2012,
        'make' => 'Toyota',
        'model' => 'Corolla',
        'plate' => 'IDLE1',
    ]);
    \App\Ark\Operations\RepairOrders\RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $openVehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Brakes',
    ]);

    $this->actingAs($advisor)
        ->get(route('operations.vehicles.search', ['work' => 'open']))
        ->assertOk()
        ->assertSee('2021 Chevy Silverado')
        ->assertDontSee('2012 Toyota Corolla');
});

test('vehicle search finds by customer name', function () {
    $customer = Customer::query()->create([
        'first_name' => 'Maricruz',
        'last_name' => 'Olivas',
        'phone' => '555-8181',
    ]);

    Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2015,
        'make' => 'Toyota',
        'model' => 'Camry',
        'plate' => 'CO-5521',
    ]);

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->get(route('operations.vehicles.search', ['q' => 'maricruz']))
        ->assertOk()
        ->assertSee('2015 Toyota Camry')
        ->assertSee('Maricruz Olivas');
});

test('vehicle search card shows active repair order footnote', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $customer = Customer::query()->create([
        'first_name' => 'Michael',
        'last_name' => 'Higashi',
        'phone' => '555-0101',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2020,
        'make' => 'Honda',
        'model' => 'Pilot',
        'plate' => 'PILOT1',
    ]);

    $repairOrder = \App\Ark\Operations\RepairOrders\RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Oil change',
    ]);

    $this->actingAs($advisor)
        ->get(route('operations.vehicles.search', ['q' => 'PILOT1']))
        ->assertOk()
        ->assertSee('RO #'.$repairOrder->repair_order_id)
        ->assertSee($repairOrder->status->label());
});
