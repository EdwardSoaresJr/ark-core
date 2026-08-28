<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

test('repair order identity band opens customer and vehicle through workspace modal', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(actingAsLearnCurrentAdvisor());

    [$repairOrder] = identityHeaderRepairOrderFixture();

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('ops-service-lane-band', false)
        ->assertSee('data-identity-present="customer"', false)
        ->assertSee('data-identity-present="vehicle"', false)
        ->assertSee("task: 'customer-identity'", false)
        ->assertSee("task: 'vehicle-identity'", false)
        ->assertSee('ops-identity-title-link', false)
        ->assertSee('data-workspace-modal-form="customer-identity"', false)
        ->assertSee('data-workspace-modal-form="vehicle-identity"', false)
        ->assertSee('Decode VIN', false)
        ->assertSee('Decode plate', false)
        ->assertSee('name="trim"', false)
        ->assertSee('name="engine"', false)
        ->assertSee('name="drive"', false)
        ->assertSee('name="transmission"', false)
        ->assertDontSee('ops-identity-edit-panel', false)
        ->assertSee('Retail', false);
});

test('closed repair order still opens vehicle identity through workspace modal', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(actingAsLearnCurrentAdvisor());

    [$repairOrder] = identityHeaderRepairOrderFixture();
    $repairOrder->forceFill(['status' => \App\Ark\Operations\RepairOrders\RepairOrderStatus::Closed])->save();

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee("task: 'vehicle-identity'", false)
        ->assertSee('data-workspace-modal-form="vehicle-identity"', false)
        ->assertSee('data-workspace-modal-form="customer-identity"', false)
        ->assertDontSee(
            route('operations.customers.show', [
                'customer' => $repairOrder->customer,
                'vehicle' => $repairOrder->vehicle_id,
            ]),
            false,
        );
});

test('customer hub vehicle deep link opens edit workspace modal', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(actingAsLearnCurrentAdvisor());

    [$repairOrder] = identityHeaderRepairOrderFixture();
    $customer = $repairOrder->customer;
    $vehicle = $repairOrder->vehicle;

    $this->get(route('operations.customers.show', ['customer' => $customer, 'vehicle' => $vehicle->id]))
        ->assertOk()
        ->assertSee('hub-vehicle', false)
        ->assertSee('vehicleId', false)
        ->assertSee('data-workspace-modal-form="hub-vehicle"', false)
        ->assertSee('Decode VIN', false)
        ->assertSee((string) $vehicle->id, false);
});

test('customer and vehicle identity updates persist globally and refresh identity json', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(actingAsLearnCurrentAdvisor());

    [$repairOrder] = identityHeaderRepairOrderFixture();
    $customer = $repairOrder->customer;
    $vehicle = $repairOrder->vehicle;

    $this->patchJson(route('operations.customers.update', $customer), [
        'first_name' => 'Rosa',
        'last_name' => 'Garcia',
        'phone' => '7195550101',
        'email' => 'rosa@example.test',
        'address_line_1' => '103 Barnes Rd',
        'city' => 'Pueblo',
        'state' => 'CO',
        'postal_code' => '81003',
        'customer_type' => $customer->customer_type ?: 'Retail',
        'notes' => $customer->notes,
        'repair_order_id' => $repairOrder->id,
    ])
        ->assertOk()
        ->assertJsonPath('customer.title', 'Rosa Garcia')
        ->assertJsonPath('customer.type', 'Retail')
        ->assertJsonFragment(['label' => 'Address', 'value' => '103 Barnes Rd'])
        ->assertJsonPath('customer.lines', fn ($lines) => collect($lines)->contains(fn ($line) => $line['label'] === 'Address' && $line['value'] === '103 Barnes Rd'));

    expect(Customer::query()->findOrFail($customer->id)->name)->toBe('Rosa Garcia');

    $this->patchJson(route('operations.customers.update', $customer), [
        'first_name' => 'Rosa',
        'last_name' => 'Garcia',
        'phone' => '7195550101',
        'email' => 'rosa@example.test',
        'customer_type' => 'Military',
        'notes' => $customer->notes,
        'repair_order_id' => $repairOrder->id,
    ])
        ->assertOk()
        ->assertJsonPath('customer.type', 'Military');

    expect(Customer::query()->findOrFail($customer->id)->customer_type)->toBe('Military');

    $this->patchJson(route('operations.customers.vehicles.update', [$customer, $vehicle]), [
        'year' => 2012,
        'make' => 'Honda',
        'model' => 'Pilot',
        'vin' => $vehicle->vin,
        'plate' => 'NEW123',
        'plate_state' => 'CO',
        'trim' => 'EX-L',
        'engine' => '3.5L V6',
        'transmission' => 'Automatic',
        'drive' => 'AWD',
        'color' => $vehicle->color,
        'nickname' => $vehicle->nickname,
        'public_notes' => $vehicle->public_notes,
        'private_notes' => $vehicle->private_notes,
        'repair_order_id' => $repairOrder->id,
    ])
        ->assertOk()
        ->assertJsonPath('vehicle.title', '2012 Honda Pilot EX-L');

    $vehicle->refresh();

    expect($vehicle->make)->toBe('Honda')
        ->and($vehicle->plate)->toBe('NEW123')
        ->and($vehicle->trim)->toBe('EX-L')
        ->and($vehicle->engine)->toBe('3.5L V6')
        ->and($vehicle->drive)->toBe('AWD');

    $this->patchJson(route('operations.customers.vehicles.update', [$customer, $vehicle->fresh()]), [
        'year' => 2012,
        'make' => 'Honda',
        'model' => 'Pilot',
        'vin' => $vehicle->vin,
        'plate' => 'NEW123',
        'plate_state' => 'CO',
        'trim' => $vehicle->trim,
        'engine' => $vehicle->engine,
        'transmission' => $vehicle->transmission,
        'drive' => $vehicle->drive,
        'color' => 'Silver',
        'nickname' => 'Family Pilot',
        'public_notes' => $vehicle->public_notes,
        'private_notes' => $vehicle->private_notes,
        'repair_order_id' => $repairOrder->id,
    ])
        ->assertOk()
        ->assertJsonPath('vehicle.title', 'Family Pilot')
        ->assertJsonPath('vehicle.subtitle', '2012 Honda Pilot EX-L · Silver · 3.5L V6');

    $vehicle->refresh();

    expect($vehicle->color)->toBe('Silver')
        ->and($vehicle->nickname)->toBe('Family Pilot');
});

test('repair order identity customer save accepts shop repair order id from the workspace', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(actingAsLearnCurrentAdvisor());

    [$repairOrder] = identityHeaderRepairOrderFixture();
    $customer = $repairOrder->customer;

    $this->postJson(route('operations.customers.update', $customer), [
        '_method' => 'PATCH',
        'first_name' => 'Updated',
        'last_name' => 'Customer',
        'phone' => $customer->phone,
        'email' => $customer->email,
        'customer_type' => $customer->customer_type ?: 'Retail',
        'notes' => $customer->notes,
        'repair_order_id' => $repairOrder->id,
    ])
        ->assertOk()
        ->assertJsonPath('customer.title', 'Updated Customer');

    $this->postJson(route('operations.customers.update', $customer->fresh()), [
        '_method' => 'PATCH',
        'first_name' => 'Shop',
        'last_name' => 'Number',
        'phone' => $customer->phone,
        'email' => $customer->email,
        'customer_type' => $customer->customer_type ?: 'Retail',
        'notes' => $customer->notes,
        'repair_order_id' => $repairOrder->repair_order_id,
    ])
        ->assertOk()
        ->assertJsonPath('customer.title', 'Shop Number');
});

test('worksheet ajax customer identity save returns json and posts shop repair order id', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(actingAsLearnCurrentAdvisor());

    [$repairOrder] = identityHeaderRepairOrderFixture();
    $customer = $repairOrder->customer;

    // Shop RO numbers diverge from internal ids in production; force that here.
    $repairOrder->forceFill(['repair_order_id' => ((int) $repairOrder->id) + 9000])->save();
    $repairOrder->refresh();

    expect($repairOrder->id)->not->toBe($repairOrder->repair_order_id);

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('name="repair_order_id" value="'.$repairOrder->repair_order_id.'"', false)
        ->assertDontSee('name="repair_order_id" value="'.$repairOrder->id.'"', false);

    $this->withHeaders([
        'X-Requested-With' => 'XMLHttpRequest',
        'Accept' => 'text/html',
    ])->patch(route('operations.customers.update', $customer), [
        'first_name' => 'Ajax',
        'last_name' => 'Customer',
        'phone' => $customer->phone,
        'email' => $customer->email,
        'customer_type' => $customer->customer_type ?: 'Retail',
        'notes' => $customer->notes,
        'repair_order_id' => $repairOrder->repair_order_id,
    ])
        ->assertOk()
        ->assertJsonPath('status', 'Customer updated.')
        ->assertJsonPath('customer.title', 'Ajax Customer');
});
