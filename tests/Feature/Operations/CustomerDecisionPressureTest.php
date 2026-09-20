<?php

use App\Ark\Operations\Approvals\ApprovalEvent;
use App\Ark\Operations\Approvals\ApprovalSource;
use App\Ark\Operations\Approvals\ApprovalType;
use App\Ark\Operations\Attention\CustomerDecisionPressure;
use App\Ark\Operations\Communications\CommunicationEventRecorder;
use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Communications\OperationalCommunicationType;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderLostReason;
use App\Ark\Operations\RepairOrders\RepairOrderPaymentStatus;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
});

test('attention surfaces customer decision pressure sorted by dollars at risk', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $this->actingAs($advisor);

    $small = decisionPressureRepairOrder(
        firstName: 'Frank',
        lastName: 'Walters',
        status: RepairOrderStatus::Estimate,
        lineCents: 140_900,
    );

    $large = decisionPressureRepairOrder(
        firstName: 'Maricruz',
        lastName: 'Olivas',
        status: RepairOrderStatus::WaitingApproval,
        lineCents: 900_644,
    );

    $this->get(route('operations.work.queue', 'decisions'))
        ->assertOk()
        ->assertSee('Customer Decisions')
        ->assertSee('Maricruz Olivas')
        ->assertSee('Frank Walters')
        ->assertSee('$9,006')
        ->assertSee('$1,409');

    $projection = app(CustomerDecisionPressure::class)->resolve();

    expect($projection['customer_decision_needed'][0]['customer_name'])->toBe('Maricruz Olivas')
        ->and($projection['estimate_ready_not_sent'][0]['customer_name'])->toBe('Frank Walters');
});

test('estimate ready not sent excludes awaiting approval repair orders', function () {
    decisionPressureRepairOrder(
        firstName: 'Justine',
        lastName: 'Whanger',
        status: RepairOrderStatus::WaitingApproval,
        lineCents: 49_500,
    );

    $projection = app(CustomerDecisionPressure::class)->resolve();

    expect($projection['estimate_ready_not_sent'])->toBe([])
        ->and($projection['customer_decision_needed'])->toHaveCount(1);
});

test('approved work stalled surfaces completed unpaid repair orders', function () {
    $repairOrder = decisionPressureRepairOrder(
        firstName: 'Dallas',
        lastName: 'Martinez',
        status: RepairOrderStatus::Completed,
        lineCents: 298_046,
        disposition: RepairOrderConcernDisposition::Approved,
    );

    $projection = app(CustomerDecisionPressure::class)->resolve();

    expect($projection['approved_work_stalled'])->toHaveCount(1)
        ->and($projection['approved_work_stalled'][0]['customer_name'])->toBe('Dallas Martinez')
        ->and($projection['approved_work_stalled'][0]['dollars_at_risk_label'])->toBe('$2,980');
});

test('closing lost requires a coded lost reason with attribution', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $this->actingAs($advisor);

    $repairOrder = decisionPressureRepairOrder(
        firstName: 'Guy',
        lastName: 'Bibi',
        status: RepairOrderStatus::WaitingApproval,
        lineCents: 45_375,
    );

    $this->patch(route('operations.repair-orders.lifecycle.update', $repairOrder), [
        'status' => 'closed:lost',
    ])->assertRedirect()
        ->assertSessionHasErrors(['lost_reason_key']);

    $this->patch(route('operations.repair-orders.lifecycle.update', $repairOrder), [
        'status' => 'closed:lost',
        'lost_reason_key' => RepairOrderLostReason::NoResponse->value,
    ])->assertRedirect();

    $repairOrder = $repairOrder->fresh();

    expect($repairOrder->status->is(RepairOrderStatus::Closed))->toBeTrue()
        ->and($repairOrder->close_variant_key)->toBe('lost')
        ->and($repairOrder->lost_reason_key)->toBe(RepairOrderLostReason::NoResponse)
        ->and($repairOrder->lost_reason_recorded_by)->toBe($advisor->id)
        ->and($repairOrder->lost_reason_recorded_at)->not->toBeNull();
});

test('estimate sent communication moves repair order into customer decision needed', function () {
    $advisor = actingAsLearnCurrentAdvisor();

    $repairOrder = decisionPressureRepairOrder(
        firstName: 'Frank',
        lastName: 'Walters',
        status: RepairOrderStatus::Estimate,
        lineCents: 140_900,
    );

    app(CommunicationEventRecorder::class)->record(
        $repairOrder,
        OperationalCommunicationType::EstimateSent,
        OperationalCommunicationChannel::Sms,
        OperationalCommunicationDirection::Outbound,
        summary: 'Estimate link sent',
        actor: $advisor,
    );

    $projection = app(CustomerDecisionPressure::class)->resolve();

    expect($projection['estimate_ready_not_sent'])->toBe([])
        ->and($projection['customer_decision_needed'])->toHaveCount(1);
});

test('customer decision pressure surfaces last customer activity from portal views', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-10 14:00:00'));

    $repairOrder = decisionPressureRepairOrder(
        firstName: 'Maricruz',
        lastName: 'Olivas',
        status: RepairOrderStatus::WaitingApproval,
        lineCents: 900_644,
    );

    app(CommunicationEventRecorder::class)->record(
        $repairOrder,
        OperationalCommunicationType::EstimateSent,
        OperationalCommunicationChannel::Email,
        OperationalCommunicationDirection::Outbound,
        summary: 'Estimate emailed',
        actor: actingAsLearnCurrentAdvisor(),
        occurredAt: Carbon::parse('2026-05-20 09:00:00'),
    );

    app(CommunicationEventRecorder::class)->record(
        $repairOrder,
        OperationalCommunicationType::EstimateViewed,
        OperationalCommunicationChannel::Website,
        OperationalCommunicationDirection::Inbound,
        summary: 'Customer opened the estimate portal link.',
        occurredAt: Carbon::parse('2026-06-10 13:13:00'),
    );

    $row = app(CustomerDecisionPressure::class)->resolve()['customer_decision_needed'][0];

    expect($row['last_customer_activity'])->toBe('Viewed estimate 47 minutes ago')
        ->and($row['age_context'])->toBe('since_estimate_sent')
        ->and($row['detail'])->toBe('Last shop contact: 21 days ago');

    Carbon::setTestNow();
});

test('customer decision pressure shows never viewed estimate when sent but no portal activity', function () {
    $repairOrder = decisionPressureRepairOrder(
        firstName: 'Benjamin',
        lastName: 'Trainee',
        status: RepairOrderStatus::WaitingApproval,
        lineCents: 250_000,
    );

    app(CommunicationEventRecorder::class)->record(
        $repairOrder,
        OperationalCommunicationType::EstimateSent,
        OperationalCommunicationChannel::Sms,
        OperationalCommunicationDirection::Outbound,
        summary: 'Estimate link sent',
        actor: actingAsLearnCurrentAdvisor(),
    );

    $row = app(CustomerDecisionPressure::class)->resolve()['customer_decision_needed'][0];

    expect($row['last_customer_activity'])->toBe('Never viewed estimate');
});

test('closed lost repair order shows lost reason on review surface', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $this->actingAs($advisor);

    $repairOrder = decisionPressureRepairOrder(
        firstName: 'Guy',
        lastName: 'Bibi',
        status: RepairOrderStatus::WaitingApproval,
        lineCents: 45_375,
    );

    $this->patch(route('operations.repair-orders.lifecycle.update', $repairOrder), [
        'status' => 'closed:lost',
        'lost_reason_key' => RepairOrderLostReason::WentElsewhere->value,
        'lost_reason_note' => 'Customer chose another shop.',
    ])->assertRedirect();

    $this->get(route('operations.repair-orders.show', $repairOrder->fresh()))
        ->assertOk()
        ->assertSee('Closed lost')
        ->assertSee('Went elsewhere')
        ->assertSee('Customer chose another shop.');
});

test('scheduled customer decision is hidden until the day before', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-10 09:00:00'));
    $advisor = actingAsLearnCurrentAdvisor();

    $repairOrder = decisionPressureRepairOrder(
        firstName: 'Ben',
        lastName: 'Trainee',
        status: RepairOrderStatus::WaitingApproval,
        lineCents: 250_000,
    );

    $this->actingAs($advisor)
        ->post(route('operations.work.decision-schedules.store'), [
            'repair_order_shop_number' => $repairOrder->repair_order_id,
            'scheduled_for' => '2026-06-15',
            'notes' => 'Waiting on payday',
        ])
        ->assertRedirect();

    $this->actingAs($advisor)
        ->get(route('operations.work.queue', 'scheduled'))
        ->assertOk()
        ->assertSee('Scheduled')
        ->assertSee('Ben Trainee')
        ->assertSee('Waiting on payday')
        ->assertSee('Upcoming')
        ->assertDontSee('Scheduled later', false)
        ->assertDontSee('Waiting on scheduled day', false);

    $projection = app(CustomerDecisionPressure::class)->resolve();

    expect($projection['customer_decision_needed'])->toBe([])
        ->and($projection['scheduled_later'])->toHaveCount(1)
        ->and($projection['scheduled_later'][0]['customer_name'])->toBe('Ben Trainee');

    Carbon::setTestNow(Carbon::parse('2026-06-14 09:00:00'));

    $projection = app(CustomerDecisionPressure::class)->resolve();

    expect($projection['customer_decision_needed'])->toHaveCount(1)
        ->and($projection['customer_decision_needed'][0]['detail'])->toContain('Scheduled tomorrow')
        ->and($projection['scheduled_later'])->toBe([]);

    Carbon::setTestNow();
});

test('customer-wide decision schedule hides all open decisions for that customer', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-10 09:00:00'));
    $advisor = actingAsLearnCurrentAdvisor();

    $first = decisionPressureRepairOrder(
        firstName: 'Jordan',
        lastName: 'Lee',
        status: RepairOrderStatus::WaitingApproval,
        lineCents: 100_000,
    );

    $vehicle = Vehicle::query()->create([
        'customer_id' => $first->customer_id,
        'plate' => 'JL2',
        'year' => 2020,
        'make' => 'Honda',
        'model' => 'Pilot',
    ]);

    $second = RepairOrder::query()->create([
        'customer_id' => $first->customer_id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Second open decision.',
    ]);

    $secondConcern = RepairOrderConcern::query()->create([
        'repair_order_id' => $second->id,
        'summary' => 'Second concern',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'position' => 1,
    ]);

    $second->lines()->create([
        'repair_order_concern_id' => $secondConcern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Labor',
        'quantity' => '1.00',
        'unit_price_cents' => 50_000,
        'subtotal_cents' => 50_000,
        'total_cents' => 50_000,
    ]);

    $this->actingAs($advisor)
        ->post(route('operations.work.decision-schedules.store'), [
            'repair_order_shop_number' => $first->repair_order_id,
            'scheduled_for' => '2026-06-20',
            'schedule_customer' => '1',
        ])
        ->assertRedirect(route('operations.index'));

    $projection = app(CustomerDecisionPressure::class)->resolve();

    expect($projection['total_count'])->toBe(0)
        ->and($projection['scheduled_later'])->toHaveCount(2);

    Carbon::setTestNow();
});

test('attention paid exclusion ignores stale payment_status mirrors', function () {
    // Mirror paid + ledger unpaid → still visible (intentional: no false calm).
    $stalePaidMirror = decisionPressureRepairOrder(
        firstName: 'Stale',
        lastName: 'PaidMirror',
        status: RepairOrderStatus::Completed,
        lineCents: 125_000,
        disposition: RepairOrderConcernDisposition::Approved,
    );
    $stalePaidMirror->forceFill([
        'payment_status' => RepairOrderPaymentStatus::Paid,
        'paid_at' => now(),
    ])->save();

    expect($stalePaidMirror->fresh()->paymentStatus())->toBe(RepairOrderPaymentStatus::Paid)
        ->and($stalePaidMirror->fresh()->isPaid())->toBeFalse();

    // Mirror unpaid + ledger paid → excluded.
    $staleUnpaidMirror = decisionPressureRepairOrder(
        firstName: 'Stale',
        lastName: 'UnpaidMirror',
        status: RepairOrderStatus::ReadyPickup,
        lineCents: 15_000,
        disposition: RepairOrderConcernDisposition::Approved,
    );
    issueFinalInvoiceFor($staleUnpaidMirror);
    payRepairOrderInFull($staleUnpaidMirror->fresh());
    $staleUnpaidMirror->fresh()->forceFill([
        'payment_status' => RepairOrderPaymentStatus::Unpaid,
        'paid_at' => null,
    ])->save();

    expect($staleUnpaidMirror->fresh()->paymentStatus())->toBe(RepairOrderPaymentStatus::Unpaid)
        ->and($staleUnpaidMirror->fresh()->isPaid())->toBeTrue();

    // Both unpaid → visible.
    $bothUnpaid = decisionPressureRepairOrder(
        firstName: 'Both',
        lastName: 'Unpaid',
        status: RepairOrderStatus::Completed,
        lineCents: 88_000,
        disposition: RepairOrderConcernDisposition::Approved,
    );
    expect($bothUnpaid->fresh()->paymentStatus())->toBe(RepairOrderPaymentStatus::Unpaid)
        ->and($bothUnpaid->fresh()->isPaid())->toBeFalse();

    // Both paid → excluded.
    $bothPaid = decisionPressureRepairOrder(
        firstName: 'Both',
        lastName: 'Paid',
        status: RepairOrderStatus::ReadyPickup,
        lineCents: 15_000,
        disposition: RepairOrderConcernDisposition::Approved,
    );
    issueFinalInvoiceFor($bothPaid);
    payRepairOrderInFull($bothPaid->fresh());
    expect($bothPaid->fresh()->paymentStatus())->toBe(RepairOrderPaymentStatus::Paid)
        ->and($bothPaid->fresh()->isPaid())->toBeTrue();

    $projection = app(CustomerDecisionPressure::class)->resolve();
    $names = collect([
        ...$projection['estimate_ready_not_sent'],
        ...$projection['customer_decision_needed'],
        ...$projection['approved_work_stalled'],
        ...$projection['scheduled_later'],
    ])->pluck('customer_name');

    expect($names)->toContain('Stale PaidMirror')
        ->and($names)->not->toContain('Stale UnpaidMirror')
        ->and($names)->toContain('Both Unpaid')
        ->and($names)->not->toContain('Both Paid');
});
