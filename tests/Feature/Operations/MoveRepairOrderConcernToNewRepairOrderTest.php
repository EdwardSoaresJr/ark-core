<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Events\OperationalEvent;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\RepairOrders\RepairOrderWorkGroup;
use App\Ark\Operations\Vehicles\Vehicle;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
});

/**
 * @return array{0: RepairOrder, 1: RepairOrderConcern, 2: RepairOrderConcern, 3: RepairOrderLine}
 */
function moveScopeSourceRepairOrder(
    RepairOrderStatus $status = RepairOrderStatus::WaitingApproval,
    RepairOrderConcernDisposition $movedDisposition = RepairOrderConcernDisposition::Recommended,
): array {
    $customer = Customer::query()->create([
        'first_name' => 'Scope',
        'last_name' => 'Move',
        'phone' => '555-0144',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2020,
        'make' => 'Toyota',
        'model' => 'Camry',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => $status,
        'concern_summary' => 'Brakes and oil',
        'opened_at' => now(),
        'estimate_version' => 1,
    ]);

    $keep = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Oil change',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'position' => 1,
    ]);

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $keep->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Oil service',
        'quantity' => '1.00',
        'unit_price_cents' => 7900,
        'subtotal_cents' => 7900,
        'total_cents' => 7900,
    ]);

    $move = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Brake noise',
        'disposition' => $movedDisposition,
        'position' => 2,
    ]);

    $workGroup = RepairOrderWorkGroup::query()->create([
        'repair_order_concern_id' => $move->id,
        'title' => 'Replace pads',
        'position' => 1,
    ]);

    $movedLine = RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $move->id,
        'repair_order_work_group_id' => $workGroup->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Brake pads',
        'quantity' => '1.00',
        'unit_price_cents' => 25000,
        'subtotal_cents' => 25000,
        'total_cents' => 25000,
    ]);

    app(EstimateTotalsCalculator::class)->recalculateRepairOrder($repairOrder->fresh());

    return [$repairOrder->fresh(), $keep->fresh(), $move->fresh(), $movedLine->fresh()];
}

test('moving a scope reparents concern lines and work group onto a new draft RO', function () {
    [$source, $keep, $move, $movedLine] = moveScopeSourceRepairOrder();
    $advisor = actingAsLearnCurrentAdvisor();

    $this->actingAs($advisor)
        ->post(route('operations.repair-orders.concerns.move-to-new-ro', [$source, $move]), [
            'opened_estimate_version' => $source->estimate_version,
        ])
        ->assertRedirect();

    $move = $move->fresh();
    $newRo = RepairOrder::query()->findOrFail($move->repair_order_id);

    expect($newRo->id)->not->toBe($source->id)
        ->and($newRo->status->is(RepairOrderStatus::Draft))->toBeTrue()
        ->and($newRo->customer_id)->toBe($source->customer_id)
        ->and($newRo->vehicle_id)->toBe($source->vehicle_id)
        ->and($move->position)->toBe(1)
        ->and($movedLine->fresh()->repair_order_id)->toBe($newRo->id)
        ->and($movedLine->fresh()->repair_order_concern_id)->toBe($move->id)
        ->and($keep->fresh()->repair_order_id)->toBe($source->id)
        ->and($source->fresh()->concerns)->toHaveCount(1)
        ->and($newRo->concerns)->toHaveCount(1);

    $this->actingAs($advisor)
        ->get(route('operations.repair-orders.show', $newRo))
        ->assertOk()
        ->assertSee('Brake noise', false)
        ->assertSee('Replace pads', false);

    expect(OperationalEvent::query()
        ->where('event_name', OperationalEventName::ConcernMovedToNewRepairOrder->value)
        ->count())->toBe(2);
});

test('cannot move a scope after a final invoice is issued', function () {
    $repairOrder = financialCloseoutRepairOrder(RepairOrderStatus::ReadyPickup);
    $concern = $repairOrder->concerns()->firstOrFail();
    issueFinalInvoiceFor($repairOrder->fresh());
    $advisor = actingAsLearnCurrentAdvisor();

    $this->actingAs($advisor)
        ->from(route('operations.repair-orders.show', $repairOrder))
        ->post(route('operations.repair-orders.concerns.move-to-new-ro', [$repairOrder, $concern]), [
            'opened_estimate_version' => $repairOrder->fresh()->estimate_version,
        ])
        ->assertRedirect(route('operations.repair-orders.show', $repairOrder))
        ->assertSessionHasErrors('concern');

    expect($concern->fresh()->repair_order_id)->toBe($repairOrder->id)
        ->and(RepairOrder::query()->where('customer_id', $repairOrder->customer_id)->count())->toBe(1);
});

test('cannot move a scope from a terminal repair order', function () {
    [$source, , $move] = moveScopeSourceRepairOrder(RepairOrderStatus::Closed);
    $advisor = actingAsLearnCurrentAdvisor();

    $this->actingAs($advisor)
        ->post(route('operations.repair-orders.concerns.move-to-new-ro', [$source, $move]), [
            'opened_estimate_version' => $source->estimate_version,
        ])
        ->assertStatus(423);

    expect($move->fresh()->repair_order_id)->toBe($source->id);
});

test('moving the only approved scope retreats lifecycle when source has no approved work left', function () {
    [$source, $keep, $move] = moveScopeSourceRepairOrder(
        status: RepairOrderStatus::Approved,
        movedDisposition: RepairOrderConcernDisposition::Approved,
    );

    $keep->update(['disposition' => RepairOrderConcernDisposition::Deferred]);
    $advisor = actingAsLearnCurrentAdvisor();

    $this->actingAs($advisor)
        ->post(route('operations.repair-orders.concerns.move-to-new-ro', [$source, $move]), [
            'opened_estimate_version' => $source->estimate_version,
        ])
        ->assertRedirect();

    expect($source->fresh()->status->is(RepairOrderStatus::WaitingApproval))->toBeTrue()
        ->and($move->fresh()->repair_order_id)->not->toBe($source->id)
        ->and($move->fresh()->disposition)->toBe(RepairOrderConcernDisposition::Approved);
});

test('builder surfaces move to new RO for open pre-invoice scopes', function () {
    [$source] = moveScopeSourceRepairOrder();

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->get(route('operations.repair-orders.show', $source))
        ->assertOk()
        ->assertSee('Move to new RO', false)
        ->assertSee(route('operations.repair-orders.concerns.move-to-new-ro', [$source, $source->concerns()->orderByDesc('position')->first()]), false);
});
