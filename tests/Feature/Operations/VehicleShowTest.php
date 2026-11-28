<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

test('vehicle show uses the shared Tabler shell and identity', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $user = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $customer = Customer::query()->create([
        'first_name' => 'Jordan',
        'last_name' => 'Hale',
        'phone' => '3035550142',
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2018,
        'make' => 'Subaru',
        'model' => 'Outback',
        'trim' => '2.5i Premium',
        'vin' => '4S4BSAFC5J3XXXXXX',
        'plate' => '7KMT42',
        'plate_state' => 'CO',
    ]);

    $this->actingAs($user)
        ->get(route('operations.vehicles.show', $vehicle))
        ->assertOk()
        ->assertSee('data-ark-topbar-global', false)
        ->assertSee('data-bs-toggle="sidebar-folded"', false)
        ->assertSee('data-ops-global-search-open', false)
        ->assertSee('Jordan Hale', false)
        ->assertSee('2018 Subaru Outback', false)
        ->assertSee('Create RO', false)
        ->assertSee('Current work', false)
        ->assertSee('Service history', false)
        ->assertSee('vehicle:'.$vehicle->id, false);
});

test('vehicle show requires vehicles.manage', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $user = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $customer = Customer::query()->create([
        'first_name' => 'No',
        'last_name' => 'Access',
        'phone' => '3035550199',
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2015,
        'make' => 'Honda',
        'model' => 'Civic',
    ]);

    $this->actingAs($user)
        ->get(route('operations.vehicles.show', $vehicle))
        ->assertForbidden();
});

test('vehicle show lists every active RO even when older than the history window', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $user = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $customer = Customer::query()->create([
        'first_name' => 'Active',
        'last_name' => 'Work',
        'phone' => '3035550188',
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2016,
        'make' => 'Toyota',
        'model' => 'Camry',
    ]);

    // Thirteen closed visits would exhaust a naive latest-12 load.
    foreach (range(1, 13) as $n) {
        RepairOrder::query()->create([
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'repair_order_id' => 8000 + $n,
            'status' => RepairOrderStatus::Closed,
            'concern_summary' => "Closed visit {$n}",
            'created_at' => now()->subDays(40 - $n),
            'updated_at' => now()->subDays(40 - $n),
        ]);
    }

    $active = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'repair_order_id' => 8999,
        'status' => RepairOrderStatus::InProgress,
        'concern_summary' => 'Still in shop',
        'created_at' => now()->subDays(60),
        'updated_at' => now()->subDays(60),
    ]);

    $html = $this->actingAs($user)
        ->get(route('operations.vehicles.show', $vehicle))
        ->assertOk()
        ->assertSee('RO #8999', false)
        ->assertSee('edit=1', false)
        ->assertSee('vehicle='.$vehicle->id, false)
        ->getContent();

    expect($html)->toContain('RO #8999');
    expect($active->fresh()->status->value)->toBe(RepairOrderStatus::InProgress->value);
});

test('edit vehicle ignores vehicles that do not belong to the customer', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $user = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $customer = Customer::query()->create([
        'first_name' => 'Owned',
        'last_name' => 'Here',
        'phone' => '3035550166',
    ]);
    $other = Customer::query()->create([
        'first_name' => 'Other',
        'last_name' => 'Owner',
        'phone' => '3035550167',
    ]);
    $foreignVehicle = Vehicle::query()->create([
        'customer_id' => $other->id,
        'year' => 2019,
        'make' => 'Kia',
        'model' => 'Forte',
    ]);

    $html = $this->actingAs($user)
        ->get(route('operations.customers.show', [
            'customer' => $customer,
            'vehicle' => $foreignVehicle->id,
            'tab' => 'vehicles',
            'edit' => 1,
        ]))
        ->assertOk()
        ->getContent();

    expect($html)->not->toContain("initialTask: 'hub-vehicle'");
});

test('edit vehicle deep-link opens the hub editor for that vehicle', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $user = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $customer = Customer::query()->create([
        'first_name' => 'Edit',
        'last_name' => 'Target',
        'phone' => '3035550177',
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2020,
        'make' => 'Mazda',
        'model' => 'CX-5',
    ]);

    $html = $this->actingAs($user)
        ->get(route('operations.customers.show', [
            'customer' => $customer,
            'vehicle' => $vehicle->id,
            'tab' => 'vehicles',
            'edit' => 1,
        ]))
        ->assertOk()
        ->assertSee("initialTask: 'hub-vehicle'", false)
        ->getContent();

    expect($html)->toContain('vehicleId')
        ->and($html)->toContain((string) $vehicle->id);
});
