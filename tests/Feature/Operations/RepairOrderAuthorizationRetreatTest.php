<?php

use App\Ark\Operations\Approvals\ApprovalEvent;
use App\Ark\Operations\Approvals\ApprovalSource;
use App\Ark\Operations\Approvals\ApprovalType;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\RepairOrders\RetreatRepairOrderAfterAuthorizationRevocationAction;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Vehicles\Vehicle;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
});

test('repair order retreats to waiting approval when no approved work remains', function () {
    $repairOrder = repairOrderWithoutApprovedWorkFixture(RepairOrderStatus::Approved);

    app(RetreatRepairOrderAfterAuthorizationRevocationAction::class)->execute(
        $repairOrder->fresh(['concerns', 'lines.concern', 'approvalEvents.revocation']),
        reason: 'no_approved_work_reconciliation',
    );

    expect($repairOrder->fresh()->status->is(RepairOrderStatus::WaitingApproval))->toBeTrue();
});

test('retreat command fixes approved repair orders with only deferred work', function () {
    $repairOrder = repairOrderWithoutApprovedWorkFixture(RepairOrderStatus::Approved);

    ApprovalEvent::query()->create([
        'visit_id' => $repairOrder->id,
        'approval_type' => ApprovalType::Repair,
        'approved_amount_cents' => 99118,
        'source' => ApprovalSource::Portal,
        'approved_by' => 'Customer',
        'approved_at' => now(),
    ]);

    $this->artisan('ark:repair-orders:retreat-without-approved-work', [
        '--repair-order-id' => $repairOrder->repair_order_id,
    ])->assertSuccessful();

    expect($repairOrder->fresh()->status->is(RepairOrderStatus::WaitingApproval))->toBeTrue();
});

test('changing last approved scope to deferred retreats repair order lifecycle', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $repairOrder = repairOrderWithoutApprovedWorkFixture(RepairOrderStatus::Approved);
    $concern = $repairOrder->concerns->first();

    $concern->update(['disposition' => RepairOrderConcernDisposition::Approved]);

    expect($repairOrder->fresh()->status->is(RepairOrderStatus::Approved))->toBeTrue();

    $this->actingAs($advisor)
        ->patch(route('operations.repair-orders.concerns.disposition', [$repairOrder, $concern]), [
            'disposition' => RepairOrderConcernDisposition::Deferred->value,
        ])
        ->assertRedirect();

    expect($repairOrder->fresh()->status->is(RepairOrderStatus::WaitingApproval))->toBeTrue();
});

function repairOrderWithoutApprovedWorkFixture(RepairOrderStatus $status): RepairOrder
{
    $customer = Customer::query()->create([
        'first_name' => 'Emirhan',
        'last_name' => 'Cadas',
        'phone' => '555-0191',
        'email' => 'emirhan@example.test',
        'customer_type' => 'Retail',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2020,
        'make' => 'Toyota',
        'model' => 'Corolla',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => $status,
        'concern_summary' => 'Brakes',
    ]);

    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Front brakes',
        'disposition' => RepairOrderConcernDisposition::Deferred,
        'position' => 1,
    ]);

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Replace front pads',
        'quantity' => '1.00',
        'unit_price_cents' => 30060,
    ]);

    return $repairOrder->fresh(['concerns.lines', 'lines.concern']);
}
