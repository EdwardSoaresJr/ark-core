<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

test('the shop can remove a vehicle with no repair orders from the customer hub', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $customer = Customer::query()->create([
        'first_name' => 'Nina',
        'last_name' => 'Park',
        'phone' => '555-0199',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2019,
        'make' => 'Subaru',
        'model' => 'Outback',
        'plate' => 'OUT999',
    ]);

    $this->get(route('operations.customers.show', $customer))
        ->assertOk()
        ->assertSee('Remove Vehicle')
        ->assertSee('Confirm Remove', false);

    $this->delete(route('operations.customers.vehicles.destroy', [$customer, $vehicle]))
        ->assertRedirect(route('operations.customers.show', $customer))
        ->assertSessionHas('status', 'Vehicle removed · 2019 Subaru Outback');

    expect(Vehicle::query()->find($vehicle->id))->toBeNull();

    $this->get(route('operations.customers.show', $customer))
        ->assertOk()
        ->assertDontSee('OUT999')
        ->assertDontSee('Remove Vehicle');
});

test('the shop cannot remove a vehicle that has repair orders', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $customer = Customer::query()->create([
        'first_name' => 'Leo',
        'last_name' => 'Tran',
        'phone' => '555-0188',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2020,
        'make' => 'Toyota',
        'model' => 'Camry',
        'plate' => 'CAM888',
    ]);

    RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Draft,
        'concern_summary' => 'Brake noise.',
    ]);

    $this->get(route('operations.customers.show', $customer))
        ->assertOk()
        ->assertDontSee('Remove Vehicle');

    $this->delete(route('operations.customers.vehicles.destroy', [$customer, $vehicle]))
        ->assertStatus(422);

    expect(Vehicle::query()->find($vehicle->id))->not->toBeNull();
});

test('vehicle removal requires the vehicle to belong to the customer', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $customer = Customer::query()->create([
        'first_name' => 'Amy',
        'last_name' => 'Cho',
    ]);

    $otherCustomer = Customer::query()->create([
        'first_name' => 'Ben',
        'last_name' => 'Cho',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $otherCustomer->id,
        'year' => 2017,
        'make' => 'Honda',
        'model' => 'Civic',
    ]);

    $this->delete(route('operations.customers.vehicles.destroy', [$customer, $vehicle]))
        ->assertNotFound();
});
