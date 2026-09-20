<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Operations\Workstations\WorkstationPresence;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
});

test('repair order vehicle can be changed before scopes or line items exist', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $customer = Customer::query()->create([
        'first_name' => 'Vehicle',
        'last_name' => 'Swap',
        'phone' => '555-4242',
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

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $truck->id,
        'status' => RepairOrderStatus::Draft,
        'concern_summary' => '',
        'opened_at' => now(),
    ]);

    $this->actingAs($advisor)
        ->patchJson(route('operations.repair-orders.vehicle.update', $repairOrder), [
            'vehicle_id' => $sedan->id,
        ])
        ->assertOk()
        ->assertJsonPath('status', 'Vehicle updated.')
        ->assertJsonPath('vehicle.title', '2020 Honda Accord')
        ->assertJsonPath('reload', true);

    expect($repairOrder->fresh()->vehicle_id)->toBe($sedan->id);
});

test('repair order vehicle change is blocked after scopes or line items exist', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $customer = Customer::query()->create([
        'first_name' => 'Locked',
        'last_name' => 'Vehicle',
        'phone' => '555-4343',
    ]);

    $truck = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2018,
        'make' => 'Ford',
        'model' => 'F-150',
    ]);

    $sedan = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2020,
        'make' => 'Honda',
        'model' => 'Accord',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $truck->id,
        'status' => RepairOrderStatus::Draft,
        'concern_summary' => '',
        'opened_at' => now(),
    ]);

    RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Brake noise',
        'customer_states' => 'Grinding when stopping',
        'position' => 1,
    ]);

    $this->actingAs($advisor)
        ->patchJson(route('operations.repair-orders.vehicle.update', $repairOrder), [
            'vehicle_id' => $sedan->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['vehicle_id']);

    RepairOrderConcern::query()->where('repair_order_id', $repairOrder->id)->delete();

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => RepairOrderConcern::query()->create([
            'repair_order_id' => $repairOrder->id,
            'summary' => 'Oil change',
            'position' => 1,
        ])->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Oil and filter',
        'quantity' => '1.00',
        'unit_price_cents' => 8900,
        'subtotal_cents' => 8900,
        'total_cents' => 8900,
    ]);

    $this->actingAs($advisor)
        ->patchJson(route('operations.repair-orders.vehicle.update', $repairOrder), [
            'vehicle_id' => $sedan->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['vehicle_id']);
});

test('repair order workspace exposes vehicle change affordance before scopes or line items', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $customer = Customer::query()->create([
        'first_name' => 'Picker',
        'last_name' => 'Test',
        'phone' => '555-4444',
    ]);

    Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2018,
        'make' => 'Ford',
        'model' => 'F-150',
    ]);

    Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2020,
        'make' => 'Honda',
        'model' => 'Accord',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $customer->vehicles()->orderBy('id')->first()->id,
        'status' => RepairOrderStatus::Draft,
        'concern_summary' => '',
        'opened_at' => now(),
    ]);

    $this->actingAs($advisor)
        ->withSession([WorkstationPresence::SESSION_BIND_DISMISSED => true])
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Wrong vehicle?', false)
        ->assertSee('arkRepairOrderVehicleChange', false)
        ->assertSee(route('operations.repair-orders.vehicle.update', $repairOrder), false);
});
