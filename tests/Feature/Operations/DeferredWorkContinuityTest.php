<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Documents\EstimateDocument;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

test('customer vehicle hub retains deferred work as vehicle continuity', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    [$customer, $vehicle, $priorRepairOrder] = deferredWorkVehicleFixture();

    $this->get(route('operations.customers.show', $customer))
        ->assertOk()
        ->assertSee('future-work item')
        ->assertSee('$420.00')
        ->assertSee('Safety/drivability recommendation should be revisited calmly.')
        ->assertSee('RO #'.$priorRepairOrder->repair_order_id.' · 1 Deferred')
        ->assertSee('Revisit safety/drivability recommendation at next contact')
        ->assertDontSee('sales lead')
        ->assertDontSee('campaign');
});

test('repeat vehicle RO review shows prior future work without sales pipeline posture', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    [$customer, $vehicle] = deferredWorkVehicleFixture();
    $currentRepairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Customer returned for oil service.',
    ]);

    // History is a lazy workspace tab — present on canonical Repair Order nav,
    // content loads via workspace-tabs (not the initial GET body).
    $this->get(route('operations.repair-orders.show', $currentRepairOrder))
        ->assertOk()
        ->assertSee("selectTab('history')", false)
        ->assertDontSee('Opportunity pipeline')
        ->assertDontSee('Relationship Context');

    $this->get(route('operations.repair-orders.workspace-tabs.show', [
        'repairOrder' => $currentRepairOrder,
        'tab' => 'history',
    ]))
        ->assertOk()
        ->assertSee('Vehicle History', false)
        ->assertSee('Deferred work to revisit', false)
        ->assertSee('From prior visits', false)
        ->assertSee('1 Deferred', false)
        ->assertSee('$420.00', false)
        ->assertSee('Revisit immediate attention item at next contact', false);
});

test('current deferred work feeds queue pressure and document snapshots', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    [$customer, $vehicle] = customerVehicleForDeferredWork();
    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Customer asks about brake noise.',
    ]);

    $concern = deferredConcernForRepairOrder($repairOrder, RepairOrderConcernDisposition::Deferred, 'Rear pads are near minimum.', 'Replace rear pads next visit.', 'maintenance');
    lineForDeferredWork($repairOrder, $concern, 26000);

    expect($repairOrder->fresh()->futureWorkSummary())->toBe('1 Deferred')
        ->and($repairOrder->fresh()->futureWorkSubtotalCents())->toBe(26000)
        ->and($repairOrder->fresh()->futureWorkNextAction())->toBe('Schedule maintenance due at next service');

    $this->post(route('operations.repair-orders.estimate-documents.store', $repairOrder))
        ->assertRedirect();

    $document = EstimateDocument::query()->sole();

    expect($document->snapshot_json['repair_order']['future_work_count'])->toBe(1)
        ->and($document->snapshot_json['repair_order']['future_work_subtotal'])->toBe('$260.00')
        ->and($document->snapshot_json['repair_order']['future_work_summary'])->toBe('1 Deferred')
        ->and($document->snapshot_json['repair_order']['future_work_next_action'])->toBe('Schedule maintenance due at next service')
        ->and($document->snapshot_json['totals']['total_cents'])->toBe(0);

    $this->get(route('operations.repair-orders.estimate-documents.show', [$repairOrder, $document]))
        ->assertOk()
        ->assertDontSee('Future Work Retained')
        ->assertDontSee('Deferred / Declined Value')
        ->assertDontSee('vehicle continuity');

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Deferred');
});

/**
 * @return array{0: Customer, 1: Vehicle, 2: RepairOrder}
 */
function deferredWorkVehicleFixture(): array
{
    [$customer, $vehicle] = customerVehicleForDeferredWork();

    $priorRepairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Closed,
        'concern_summary' => 'Prior inspection found front suspension play.',
        'created_at' => now()->subMonth(),
        'updated_at' => now()->subMonth(),
    ]);

    $concern = deferredConcernForRepairOrder($priorRepairOrder, RepairOrderConcernDisposition::Deferred, 'Front lower control arm bushings cracked.', 'Replace lower control arms before alignment.', 'immediate_attention');
    lineForDeferredWork($priorRepairOrder, $concern, 42000);

    return [$customer, $vehicle, $priorRepairOrder];
}

/**
 * @return array{0: Customer, 1: Vehicle}
 */
function customerVehicleForDeferredWork(): array
{
    $customer = Customer::query()->create([
        'first_name' => 'Future',
        'last_name' => 'Customer',
        'phone' => '555-0301',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2018,
        'make' => 'Toyota',
        'model' => 'RAV4',
    ]);

    return [$customer, $vehicle];
}

function deferredConcernForRepairOrder(
    RepairOrder $repairOrder,
    RepairOrderConcernDisposition $disposition,
    string $finding,
    string $recommendation,
    string $recommendationIntent,
): RepairOrderConcern {
    return RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => $finding,
        'verified_findings' => $finding,
        'recommendation' => $recommendation,
        'disposition' => $disposition,
        'recommendation_intent' => $recommendationIntent,
        'position' => 1,
    ]);
}

function lineForDeferredWork(RepairOrder $repairOrder, RepairOrderConcern $concern, int $subtotalCents): RepairOrderLine
{
    return RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Future work labor',
        'quantity' => '1.00',
        'unit_price_cents' => $subtotalCents,
        'subtotal_cents' => $subtotalCents,
        'tax_cents' => 0,
        'shop_fee_cents' => 0,
        'total_cents' => $subtotalCents,
    ]);
}
