<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Operations\Workstations\WorkstationPresence;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    session([WorkstationPresence::SESSION_BIND_DISMISSED => true]);
});

function hubCustomerWithFleet(): array
{
    $customer = Customer::query()->create([
        'first_name' => 'Hub',
        'last_name' => 'Tabler',
        'phone' => '7195550480',
        'email' => 'hub.tabler@example.test',
        'notes' => 'Prefers a call after 3pm.',
    ]);
    $servicedVehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2018,
        'make' => 'Toyota',
        'model' => 'Tacoma',
        'plate' => 'HUB480',
    ]);
    $idleVehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2009,
        'make' => 'Honda',
        'model' => 'Fit',
        'plate' => 'IDLE480',
    ]);
    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $servicedVehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Brake noise',
    ]);

    return [$customer, $servicedVehicle, $idleVehicle, $repairOrder];
}

test('the customer hub renders in the Tabler shell with its tabs and profile', function (): void {
    [$customer, $servicedVehicle] = hubCustomerWithFleet();

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->get(route('operations.customers.show', $customer))
        ->assertOk()
        ->assertSee('navbar navbar-vertical', false)
        ->assertSee('class="page-title">Hub Tabler</h2>', false)
        ->assertSee('role="group" aria-label="Customer commands"', false)
        ->assertSee('nav nav-tabs card-header-tabs', false)
        ->assertSee('x-on:click="selectTab(\'documents\')"', false)
        ->assertSee('hub.tabler@example.test')
        ->assertSee('Prefers a call after 3pm.')
        ->assertSee('id="vehicle-'.$servicedVehicle->id.'" class="card card-sm"', false)
        ->assertSee('<div class="ark-card-grid" style="--ark-card-min-width: 22rem">', false)
        ->assertDontSee('ops-hub-layout', false)
        ->assertDontSee('ops-report-tab', false)
        ->assertDontSee('container-xl', false);
});

test('only a vehicle with no repair orders offers removal', function (): void {
    [$customer, $servicedVehicle, $idleVehicle] = hubCustomerWithFleet();

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->get(route('operations.customers.show', $customer))
        ->assertOk()
        ->assertSee('x-on:click="pendingRemoveVehicleId = '.$idleVehicle->id.'"', false)
        ->assertDontSee('x-on:click="pendingRemoveVehicleId = '.$servicedVehicle->id.'"', false)
        ->assertSee('Confirm Remove');
});

test('starting a repair order from a vehicle keeps its draft form', function (): void {
    [$customer, $servicedVehicle] = hubCustomerWithFleet();

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->get(route('operations.customers.show', $customer))
        ->assertOk()
        ->assertSee('id="vehicle-ro-create-'.$servicedVehicle->id.'"', false)
        ->assertSee('action="'.route('operations.customers.repair-orders.drafts.store', $customer).'"', false)
        ->assertSee('<input type="hidden" name="vehicle_id" value="'.$servicedVehicle->id.'">', false)
        ->assertSee('Create Estimate / RO');
});

test('the history tab lists repair orders with Tabler status badges', function (): void {
    [$customer, , , $repairOrder] = hubCustomerWithFleet();

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->get(route('operations.customers.show', $customer))
        ->assertOk()
        ->assertSee('href="'.route('operations.repair-orders.show', $repairOrder).'" class="list-group-item list-group-item-action"', false)
        ->assertSee('Brake noise')
        ->assertSee('class="badge bg-', false);
});

test('shared legacy panels on the hub sit in legacy islands', function (): void {
    [$customer] = hubCustomerWithFleet();

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->get(route('operations.customers.show', $customer))
        ->assertOk()
        ->assertSee('<div class="ark-legacy bg-white text-dark">', false)
        ->assertSee('data-workspace-modal-form="hub-customer"', false)
        ->assertSee('id="conversation-messages-relationship"', false);
});

test('the documents tab offers adding a document when there are none', function (): void {
    [$customer] = hubCustomerWithFleet();

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->get(route('operations.customers.show', ['customer' => $customer, 'tab' => 'documents']))
        ->assertOk()
        ->assertSee('id="customer-documents"', false)
        ->assertSee("task: 'hub-document'", false)
        ->assertSee('No documents for this customer yet.');
});
