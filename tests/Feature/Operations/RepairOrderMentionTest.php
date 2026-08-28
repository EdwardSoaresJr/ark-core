<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Operations\Workstations\WorkstationPresence;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
});

test('intake visit reason suggests previous visits for the same customer', function () {
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $customer = Customer::query()->create([
        'first_name' => 'Prior',
        'last_name' => 'Visit',
        'phone' => '555-1677',
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2019,
        'make' => 'Honda',
        'model' => 'Civic',
    ]);
    $prior = RepairOrder::query()->create([
        'repair_order_id' => 1677,
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Closed,
        'concern_summary' => 'Front brakes grinding',
        'visit_reason' => 'Front brakes grinding',
        'opened_at' => now()->subMonth(),
    ]);

    $this->withSession([WorkstationPresence::SESSION_BIND_DISMISSED => true])
        ->get(route('operations.intake.create', [
            'ws' => 'testmention01',
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'source_repair_order_id' => $prior->id,
        ]))
        ->assertOk()
        ->assertSee('Previous visits', false)
        ->assertSee('RO 1677', false)
        ->assertSee('@RO1677', false)
        ->assertSee('Previous RO: @RO1677', false);
});

test('repair order visit reason links a same-customer @RO mention', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $customer = Customer::query()->create([
        'first_name' => 'Link',
        'last_name' => 'Mention',
        'phone' => '555-1678',
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2021,
        'make' => 'Ford',
        'model' => 'Escape',
    ]);
    $prior = RepairOrder::query()->create([
        'repair_order_id' => 1677,
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Closed,
        'concern_summary' => 'Alignment',
        'visit_reason' => 'Alignment',
        'opened_at' => now()->subWeek(),
    ]);
    $current = RepairOrder::query()->create([
        'repair_order_id' => 1680,
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Comeback',
        'visit_reason' => 'Comeback from @RO1677',
        'opened_at' => now(),
    ]);

    $this->actingAs($advisor)
        ->get(route('operations.repair-orders.show', $current))
        ->assertOk()
        ->assertSee('Comeback from', false)
        ->assertSee('href="'.route('operations.repair-orders.show', $prior).'"', false)
        ->assertSee('RO 1677', false);
});

test('repair order visit reason does not link another customer shop number', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $ours = Customer::query()->create([
        'first_name' => 'Ours',
        'last_name' => 'Customer',
        'phone' => '555-2001',
    ]);
    $theirs = Customer::query()->create([
        'first_name' => 'Theirs',
        'last_name' => 'Customer',
        'phone' => '555-2002',
    ]);
    $ourVehicle = Vehicle::query()->create([
        'customer_id' => $ours->id,
        'year' => 2018,
        'make' => 'Toyota',
        'model' => 'Corolla',
    ]);
    $theirVehicle = Vehicle::query()->create([
        'customer_id' => $theirs->id,
        'year' => 2017,
        'make' => 'Nissan',
        'model' => 'Altima',
    ]);
    RepairOrder::query()->create([
        'repair_order_id' => 2400,
        'customer_id' => $theirs->id,
        'vehicle_id' => $theirVehicle->id,
        'status' => RepairOrderStatus::Closed,
        'concern_summary' => 'Their visit',
        'opened_at' => now()->subWeek(),
    ]);
    $current = RepairOrder::query()->create([
        'repair_order_id' => 2401,
        'customer_id' => $ours->id,
        'vehicle_id' => $ourVehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Ours',
        'visit_reason' => 'Related to @RO2400',
        'opened_at' => now(),
    ]);

    $html = $this->actingAs($advisor)
        ->get(route('operations.repair-orders.show', $current))
        ->assertOk()
        ->assertSee('@RO2400', false)
        ->getContent();

    expect($html)->not->toContain('href="'.route('operations.repair-orders.show', 2400).'"');
});
