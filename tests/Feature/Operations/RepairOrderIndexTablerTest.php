<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
});

function repairOrderIndexFixture(): RepairOrder
{
    $customer = Customer::query()->create(['first_name' => 'Index', 'last_name' => 'Owner', 'phone' => '7195550601']);
    $vehicle = Vehicle::query()->create(['customer_id' => $customer->id, 'year' => 2017, 'make' => 'Subaru', 'model' => 'Outback']);

    return RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Grinding noise when braking',
    ]);
}

test('the repair order index renders in the Tabler shell with its filters', function (): void {
    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->get(route('operations.repair-orders.index'))
        ->assertOk()
        ->assertSee('navbar navbar-vertical', false)
        ->assertSee('class="page-title">Repair Orders</h2>', false)
        ->assertSee('id="ro-search"', false)
        ->assertSee('id="ro-status"', false)
        ->assertSee('id="ro-created-from"', false)
        ->assertSee('id="ro-created-to"', false)
        ->assertSee('Search active and historical ROs without changing the live workboard queue.')
        ->assertDontSee('ops-ro-retrieval-grid', false);
});

test('a repair order card links the order, vehicle, and customer with its status', function (): void {
    $repairOrder = repairOrderIndexFixture()->refresh();

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->get(route('operations.repair-orders.index'))
        ->assertOk()
        ->assertSee('class="ark-card-grid"', false)
        ->assertSee('href="'.route('operations.repair-orders.show', $repairOrder).'" class="fw-bold text-reset">RO #'.$repairOrder->repair_order_id, false)
        ->assertSee(route('operations.customers.show', ['customer' => $repairOrder->customer_id, 'vehicle' => $repairOrder->vehicle_id]), false)
        ->assertSee('Index Owner')
        ->assertSee('Grinding noise when braking')
        ->assertSee($repairOrder->statusDisplayLabel())
        ->assertSee('1 total');
});

test('queue drill-downs keep their hidden filters and an empty result offers a way back', function (): void {
    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->get(route('operations.repair-orders.index', ['unassigned' => '1', 'q' => 'nothing-matches']))
        ->assertOk()
        ->assertSee('Unassigned shop floor queue')
        ->assertSee('<input type="hidden" name="unassigned" value="1">', false)
        ->assertSee('No repair orders match these filters.')
        ->assertSee('Clear search');
});
