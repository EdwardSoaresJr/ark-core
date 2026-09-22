<?php

use App\Ark\Operations\Approvals\ApprovalEvent;
use App\Ark\Operations\Approvals\ApprovalSource;
use App\Ark\Operations\Approvals\ApprovalType;
use App\Ark\Operations\Communications\CommunicationEvent;
use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Communications\OperationalCommunicationType;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Events\OperationalEvent;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Events\OperationalEventRecorder;
use App\Ark\Operations\RepairOrders\PartProcurementState;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLifecycleProjection;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
});

test('lifecycle projection uses repair_orders.created_at for created', function () {
    $createdAt = Carbon::parse('2026-06-10 08:14:00');

    $repairOrder = lifecycleProjectionRepairOrder();
    $repairOrder->forceFill(['created_at' => $createdAt])->save();

    $created = lifecycleMilestone($repairOrder->fresh(), 'created');

    expect($created['occurred_at']->toDateTimeString())->toBe($createdAt->toDateTimeString())
        ->and($created['source'])->toBe('repair_orders.created_at')
        ->and($created['status'])->toBe('complete');
});

test('estimate sent uses communication_events not estimate_emailed operational event', function () {
    $repairOrder = lifecycleProjectionRepairOrder();
    $sentAt = Carbon::parse('2026-06-10 09:02:00');

    CommunicationEvent::query()->create([
        'repair_order_id' => $repairOrder->id,
        'event_type' => OperationalCommunicationType::EstimateSent,
        'channel' => OperationalCommunicationChannel::Email,
        'direction' => OperationalCommunicationDirection::Outbound,
        'summary' => 'Estimate emailed to customer.',
        'occurred_at' => $sentAt,
    ]);

    app(OperationalEventRecorder::class)->record(
        OperationalEventName::EstimateEmailedToCustomer,
        $repairOrder,
        payload: ['recipient_email' => 'customer@example.com'],
    );

    OperationalEvent::query()
        ->where('event_name', OperationalEventName::EstimateEmailedToCustomer->value)
        ->update(['occurred_at' => Carbon::parse('2026-06-10 10:00:00')]);

    $estimateSent = lifecycleMilestone($repairOrder->fresh(), 'estimate_sent');

    expect($estimateSent['occurred_at']->eq($sentAt))->toBeTrue()
        ->and($estimateSent['source'])->toBe('communication_events.estimate_sent');
});

test('approval uses approval_events not concern disposition changes', function () {
    $repairOrder = lifecycleProjectionRepairOrder();
    [$concern] = lifecycleProjectionConcernWithPart($repairOrder);

    app(OperationalEventRecorder::class)->record(
        OperationalEventName::ConcernDispositionChanged,
        $repairOrder,
        payload: [
            'concern_id' => $concern->id,
            'prior_disposition' => RepairOrderConcernDisposition::Recommended->value,
            'new_disposition' => RepairOrderConcernDisposition::Approved->value,
        ],
    );

    $approvedAt = Carbon::parse('2026-06-12 14:17:00');

    ApprovalEvent::query()->create([
        'visit_id' => $repairOrder->id,
        'estimate_snapshot_reference' => 'snapshot-1',
        'approval_type' => ApprovalType::Repair,
        'approved_amount_cents' => 25000,
        'source' => ApprovalSource::Phone,
        'approved_by' => 'Customer',
        'approved_at' => $approvedAt,
    ]);

    $approved = lifecycleMilestone($repairOrder->fresh(), 'approved');

    expect($approved['occurred_at']->eq($approvedAt))->toBeTrue()
        ->and($approved['source'])->toBe('approval_events.approved_at')
        ->and($approved['status'])->toBe('complete');
});

test('work completed uses lifecycle transition to ready_pickup not repair_orders.closed_at', function () {
    $repairOrder = lifecycleProjectionRepairOrder(['status' => RepairOrderStatus::ReadyPickup]);
    $readyAt = Carbon::parse('2026-06-13 16:00:00');
    $closedAt = Carbon::parse('2026-06-13 15:00:00');

    $repairOrder->forceFill(['closed_at' => $closedAt])->save();

    app(OperationalEventRecorder::class)->record(
        OperationalEventName::RepairOrderLifecycleChanged,
        $repairOrder,
        payload: [
            'from_status' => RepairOrderStatus::InProgress->value,
            'to_status' => RepairOrderStatus::ReadyPickup->value,
        ],
    );

    OperationalEvent::query()
        ->where('event_name', OperationalEventName::RepairOrderLifecycleChanged->value)
        ->update(['occurred_at' => $readyAt]);

    $workCompleted = lifecycleMilestone($repairOrder->fresh(), 'work_completed');

    expect($workCompleted['occurred_at']->eq($readyAt))->toBeTrue()
        ->and($workCompleted['occurred_at']->eq($closedAt))->toBeFalse()
        ->and($workCompleted['source'])->toBe('operational_events.repair_order_lifecycle_changed.ready_pickup');
});

test('closed uses lifecycle transition to closed', function () {
    $repairOrder = lifecycleProjectionRepairOrder(['status' => RepairOrderStatus::Closed]);
    $closedAt = Carbon::parse('2026-06-14 17:30:00');

    app(OperationalEventRecorder::class)->record(
        OperationalEventName::RepairOrderLifecycleChanged,
        $repairOrder,
        payload: [
            'from_status' => RepairOrderStatus::ReadyPickup->value,
            'to_status' => RepairOrderStatus::Closed->value,
            'close_variant_key' => 'paid',
        ],
    );

    OperationalEvent::query()
        ->where('event_name', OperationalEventName::RepairOrderLifecycleChanged->value)
        ->update(['occurred_at' => $closedAt]);

    $closed = lifecycleMilestone($repairOrder->fresh(), 'closed');

    expect($closed['occurred_at']->eq($closedAt))->toBeTrue()
        ->and($closed['source'])->toBe('operational_events.repair_order_lifecycle_changed.closed');
});

test('pending milestones render without timestamps', function () {
    $repairOrder = lifecycleProjectionRepairOrder();

    $milestones = app(RepairOrderLifecycleProjection::class)->for($repairOrder);

    expect(collect($milestones)->firstWhere('key', 'created')['occurred_at'])->not->toBeNull()
        ->and(collect($milestones)->firstWhere('key', 'estimate_sent')['occurred_at'])->toBeNull()
        ->and(collect($milestones)->firstWhere('key', 'approved')['status'])->toBe('pending')
        ->and(collect($milestones)->firstWhere('key', 'closed')['status'])->toBe('pending');
});

test('parts received stays pending while procurement is unresolved and does not fabricate timestamps', function () {
    $repairOrder = lifecycleProjectionRepairOrder();
    [, $line] = lifecycleProjectionConcernWithPart($repairOrder, disposition: RepairOrderConcernDisposition::Approved);

    $orderedAt = Carbon::parse('2026-06-12 14:24:00');

    app(OperationalEventRecorder::class)->record(
        OperationalEventName::PartOrdered,
        $repairOrder,
        payload: ['line_id' => $line->id, 'to_state' => PartProcurementState::Ordered->value],
    );

    OperationalEvent::query()
        ->where('event_name', OperationalEventName::PartOrdered->value)
        ->update(['occurred_at' => $orderedAt]);

    $pending = lifecycleMilestone($repairOrder->fresh(), 'parts_received');

    expect($pending['occurred_at'])->toBeNull()
        ->and($pending['status'])->toBe('pending');

    $line->update(['procurement_state' => PartProcurementState::Partial]);

    expect(lifecycleMilestone($repairOrder->fresh(), 'parts_received')['occurred_at'])->toBeNull();
});

test('parts received uses latest part_received when all approved parts are resolved', function () {
    $repairOrder = lifecycleProjectionRepairOrder();
    [, $line] = lifecycleProjectionConcernWithPart($repairOrder, disposition: RepairOrderConcernDisposition::Approved);

    $firstReceivedAt = Carbon::parse('2026-06-12 15:00:00');
    $finalReceivedAt = Carbon::parse('2026-06-12 16:45:00');

    app(OperationalEventRecorder::class)->record(
        OperationalEventName::PartReceived,
        $repairOrder,
        payload: ['line_id' => $line->id, 'to_state' => PartProcurementState::Received->value],
    );

    OperationalEvent::query()
        ->where('event_name', OperationalEventName::PartReceived->value)
        ->update(['occurred_at' => $firstReceivedAt]);

    $line->update(['procurement_state' => PartProcurementState::Received]);

    app(OperationalEventRecorder::class)->record(
        OperationalEventName::PartReceived,
        $repairOrder,
        payload: ['line_id' => $line->id, 'to_state' => PartProcurementState::Received->value],
    );

    OperationalEvent::query()
        ->where('event_name', OperationalEventName::PartReceived->value)
        ->orderByDesc('id')
        ->limit(1)
        ->update(['occurred_at' => $finalReceivedAt]);

    $received = lifecycleMilestone($repairOrder->fresh(), 'parts_received');

    expect($received['occurred_at']->eq($finalReceivedAt))->toBeTrue()
        ->and($received['status'])->toBe('derived');
});

test('repair order workspace renders lifecycle panel', function () {
    $this->actingAs(actingAsLearnCurrentAdvisor());

    $repairOrder = lifecycleProjectionRepairOrder();

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Status history')
        ->assertSee('Pickup notified');
});

/**
 * @param  array<string, mixed>  $attributes
 */
function lifecycleProjectionRepairOrder(array $attributes = []): RepairOrder
{
    $customer = Customer::query()->create([
        'first_name' => 'Lifecycle',
        'last_name' => 'Customer',
        'phone' => '555-0199',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2020,
        'make' => 'Subaru',
        'model' => 'Outback',
    ]);

    return RepairOrder::query()->create(array_merge([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Lifecycle projection test.',
    ], $attributes));
}

/**
 * @return array{0: RepairOrderConcern, 1: RepairOrderLine}
 */
function lifecycleProjectionConcernWithPart(
    RepairOrder $repairOrder,
    RepairOrderConcernDisposition $disposition = RepairOrderConcernDisposition::Recommended,
): array {
    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Brakes',
        'disposition' => $disposition,
        'recommendation_intent' => 'immediate_attention',
        'position' => 1,
    ]);

    $line = RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Part,
        'description' => 'Front pads',
        'quantity' => '1.00',
        'unit_price_cents' => 15000,
        'part_cost_cents' => 7000,
        'procurement_state' => PartProcurementState::None,
        'subtotal_cents' => 15000,
        'tax_cents' => 0,
        'shop_fee_cents' => 0,
        'total_cents' => 15000,
    ]);

    return [$concern, $line];
}

/**
 * @return array{key: string, label: string, occurred_at: ?Carbon, source: string, status: string, note: ?string}
 */
function lifecycleMilestone(RepairOrder $repairOrder, string $key): array
{
    $milestone = collect(app(RepairOrderLifecycleProjection::class)->for($repairOrder))
        ->firstWhere('key', $key);

    expect($milestone)->not->toBeNull("Missing lifecycle milestone [{$key}]");

    return $milestone;
}
