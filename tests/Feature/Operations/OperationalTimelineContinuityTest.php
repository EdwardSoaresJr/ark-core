<?php

use App\Ark\Operations\Approvals\ApprovalEvent;
use App\Ark\Operations\Approvals\ApprovalSource;
use App\Ark\Operations\Approvals\ApprovalType;
use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Communications\OperationalCommunicationType;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Documents\EstimateDocument;
use App\Ark\Operations\RepairOrders\PartProcurementState;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Timeline\OperationalTimeline;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use Database\Seeders\ArkAuthorizationSeeder;

test('repair order timeline reads as operational history instead of audit log noise', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $advisor = actingAsLearnCurrentAdvisor();
    $technician = actingAsLearnCurrentStaff(ArkRole::Technician);
    $this->actingAs($advisor);

    [$repairOrder, $concern, $line] = timelineRepairOrderFixture();

    $this->patch(route('operations.repair-orders.concerns.disposition', [$repairOrder, $concern]), [
        'disposition' => RepairOrderConcernDisposition::Deferred->value,
    ])->assertRedirect();

    $this->patch(route('operations.repair-orders.technician-assignment.update', $repairOrder), [
        'assigned_technician_id' => $technician->id,
    ])->assertRedirect();

    $this->patch(route('operations.repair-orders.lines.procurement.update', [$repairOrder, $line]), [
        'procurement_state' => PartProcurementState::Sourcing->value,
    ])->assertRedirect();

    $this->post(route('operations.repair-orders.communications.store', $repairOrder), [
        'communication_type' => OperationalCommunicationType::ApprovalFollowUp->value,
        'channel' => OperationalCommunicationChannel::Phone->value,
        'direction' => OperationalCommunicationDirection::Outbound->value,
        'summary' => 'Explained why the recommendation can wait until next visit.',
    ])->assertRedirect();

    ApprovalEvent::query()->create([
        'visit_id' => $repairOrder->id,
        'estimate_snapshot_reference' => 'test-snapshot',
        'approval_type' => ApprovalType::Repair,
        'approved_amount_cents' => 37500,
        'source' => ApprovalSource::Phone,
        'approved_by' => 'Customer',
        'approved_at' => now(),
        'notes' => 'Customer approved front brakes by phone.',
    ]);

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertDontSee('Operational Timeline')
        ->assertDontSee('Vehicle History Signal')
        ->assertDontSee('status_id')
        ->assertDontSee('EstimateMutationEvent')
        ->assertDontSee('payload_json');

    expect(app(OperationalTimeline::class)->forRepairOrder($repairOrder->fresh(), 8))
        ->not->toBeEmpty()
        ->and(collect(app(OperationalTimeline::class)->forRepairOrder($repairOrder->fresh(), 8))->pluck('title')->all())
        ->toContain('Customer authorized Repair')
        ->toContain('Technician assigned')
        ->toContain('Part sourcing started')
        ->toContain('Approval follow-up');
});

test('customer vehicle hub shows compact operational timeline across visits', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(actingAsLearnCurrentAdvisor());

    [$repairOrder, $concern] = timelineRepairOrderFixture();

    $this->patch(route('operations.repair-orders.concerns.disposition', [$repairOrder, $concern]), [
        'disposition' => RepairOrderConcernDisposition::Deferred->value,
    ])->assertRedirect();

    $this->get(route('operations.customers.show', $repairOrder->customer))
        ->assertOk()
        ->assertSee('Operational Timeline')
        ->assertSee('RO #'.$repairOrder->repair_order_id.' · Brake recommendation deferred')
        ->assertSee('Previously Recommended')
        ->assertDontSee('activity feed');
});

test('estimate document snapshots preserve readable operational timeline', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(actingAsLearnCurrentAdvisor());

    [$repairOrder, $concern] = timelineRepairOrderFixture();

    $this->patch(route('operations.repair-orders.concerns.disposition', [$repairOrder, $concern]), [
        'disposition' => RepairOrderConcernDisposition::Approved->value,
    ])->assertRedirect();

    $this->post(route('operations.repair-orders.estimate-documents.store', $repairOrder))
        ->assertRedirect();

    $document = EstimateDocument::query()->sole();

    expect($document->snapshot_json['staff']['timeline'])->not->toBeEmpty()
        ->and(collect($document->snapshot_json['staff']['timeline'])->pluck('title')->all())->toContain('Brake recommendation approved');

    $this->get(route('operations.repair-orders.estimate-documents.show', [$repairOrder, $document]))
        ->assertOk()
        ->assertSee('Operational Timeline')
        ->assertSee('Brake recommendation approved');

    $pdfHtml = view('operations.documents.estimates.pdf', [
        'document' => $document,
        'snapshot' => $document->snapshot_json,
    ])->render();

    expect($pdfHtml)
        ->toContain('Brake recommendation')
        ->not->toContain('Operational Timeline');
});

/**
 * @return array{0: RepairOrder, 1: RepairOrderConcern, 2: RepairOrderLine}
 */
function timelineRepairOrderFixture(): array
{
    $customer = Customer::query()->create([
        'first_name' => 'Timeline',
        'last_name' => 'Customer',
        'phone' => '555-0100',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2019,
        'make' => 'Honda',
        'model' => 'Pilot',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Customer asks for brake inspection.',
    ]);

    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Brake recommendation',
        'recommendation' => 'Replace front pads and rotors.',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'recommendation_intent' => 'immediate_attention',
        'position' => 1,
    ]);

    $line = RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Part,
        'description' => 'Front brake pads',
        'quantity' => '1.00',
        'unit_price_cents' => 17500,
        'part_cost_cents' => 8500,
        'vendor_name' => 'Local Parts',
        'part_number' => 'PAD-777',
        'procurement_state' => PartProcurementState::None,
        'subtotal_cents' => 17500,
        'tax_cents' => 0,
        'shop_fee_cents' => 0,
        'total_cents' => 17500,
    ]);

    return [$repairOrder, $concern, $line];
}
