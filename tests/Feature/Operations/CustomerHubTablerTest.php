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
        ->assertSee('id="conversation-messages-relationship"', false);
});

test('the customer and vehicle edit modal is Tabler markup on the same forms and routes', function (): void {
    [$customer, $servicedVehicle] = hubCustomerWithFleet();

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->get(route('operations.customers.show', $customer))
        ->assertOk()
        ->assertSee('id="workspace-modal-host"', false)
        ->assertSee('class="modal-dialog modal-lg modal-dialog-scrollable modal-fullscreen-sm-down"', false)
        ->assertSee('action="'.route('operations.customers.update', $customer).'"', false)
        ->assertSee('data-workspace-modal-form="hub-customer"', false)
        ->assertSee('action="'.route('operations.customers.vehicles.update', [$customer, $servicedVehicle]).'"', false)
        ->assertSee('data-workspace-modal-form="hub-vehicle"', false)
        ->assertSee('action="'.route('operations.customers.vehicles.store', $customer).'"', false)
        ->assertSee('data-workspace-modal-form="hub-vehicle-create"', false)
        ->assertSee('name="customer_type"', false)
        ->assertSee('name="private_notes"', false)
        ->assertDontSee('ops-workspace-modal__dialog', false)
        ->assertDontSee('ops-workspace-modal__panel', false);
});

test('a rejected customer edit reopens the modal with the field error', function (): void {
    [$customer] = hubCustomerWithFleet();
    $hub = route('operations.customers.show', $customer);

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->from($hub)
        ->followingRedirects()
        ->patch(route('operations.customers.update', $customer), [
            'first_name' => 'Hub',
            'last_name' => 'Tabler',
            'phone' => '7195550480',
            'email' => 'not-an-email',
        ])
        ->assertOk()
        ->assertSee("initialTask: 'hub-customer'", false)
        ->assertSee('class="form-control is-invalid"', false)
        ->assertSee('The email field must be a valid email address.');

    expect($customer->fresh()->email)->toBe('hub.tabler@example.test');
});

test('a vehicle with a VIN shows the Tabler VIN display', function (): void {
    [$customer, $servicedVehicle] = hubCustomerWithFleet();
    $servicedVehicle->update(['vin' => '1HGCM82633A004352']);

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->get(route('operations.customers.show', $customer))
        ->assertOk()
        ->assertSee('data-vin-display', false)
        ->assertSee('x-data="arkVinDisplay(', false)
        ->assertSee('<span class="fw-bold">3A004352</span>', false)
        ->assertDontSee('ops-vin-trigger', false);
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
