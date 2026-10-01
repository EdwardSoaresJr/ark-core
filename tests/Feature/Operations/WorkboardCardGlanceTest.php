<?php

use App\Ark\Operations\Communications\CommunicationEvent;
use App\Ark\Operations\Communications\OperationalCommunicationType;
use App\Ark\Operations\Financial\BalanceDueResult;
use App\Ark\Operations\Financial\InvoiceStatus;
use App\Ark\Operations\RepairOrders\EstimateTotals;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Workboard\WorkboardCardGlanceProjection;
use App\Ark\Operations\Workboard\WorkboardTriageCard;
use Illuminate\Support\Carbon;

function glanceTriageCard(
    RepairOrder $repairOrder,
    ?string $signalLabel = null,
    bool $customerWaiting = false,
    bool $overduePickup = false,
    bool $unassigned = false,
): WorkboardTriageCard {
    $repairOrder->setRelation('lines', collect());
    $repairOrder->setRelation('communicationEvents', $repairOrder->relationLoaded('communicationEvents')
        ? $repairOrder->communicationEvents
        : collect());

    return new WorkboardTriageCard(
        repairOrder: $repairOrder,
        vehicleLabel: '2018 Ram 2500',
        concernSummary: 'Brake noise when stopping.',
        concernHeadline: 'Brake noise when stopping',
        signalLabel: $signalLabel,
        signalTone: $signalLabel ? 'warn' : 'neutral',
        ageLabel: '4w',
        ageMinutes: 40320,
        pressureScore: 12,
        countsAsNeedsAttention: true,
        countsAsCustomerWaiting: $customerWaiting,
        countsAsUnassigned: $unassigned,
        countsAsOverduePickup: $overduePickup,
        href: '/app/repair-orders/1',
    );
}

function glanceRepairOrder(RepairOrderStatus $status, ?Carbon $createdAt = null): RepairOrder
{
    $repairOrder = new RepairOrder;
    $repairOrder->forceFill([
        'id' => 11,
        'repair_order_id' => 2534,
        'vehicle_id' => 40,
        'status' => $status->value,
        'created_at' => $createdAt ?? now()->subDays(30),
        'updated_at' => $createdAt ?? now()->subDays(30),
    ]);

    return $repairOrder;
}

function glanceTotals(int $grossLaborCents): EstimateTotals
{
    return new EstimateTotals(collect(), [], $grossLaborCents, 0, 0, 0, 0, 0, 0, $grossLaborCents);
}

test('waiting approval without send is customer decision not a new status', function () {
    Carbon::setTestNow('2026-09-10 12:00:00');

    $repairOrder = glanceRepairOrder(RepairOrderStatus::WaitingApproval);
    $glance = (new WorkboardCardGlanceProjection)->forCard(
        glanceTriageCard($repairOrder),
        'waiting_approval',
        glanceTotals(902_700),
        null,
        now()->subDays(6),
        false,
    );

    expect($glance->waitingOnCustomerDecision)->toBeTrue()
        ->and($repairOrder->status->is(RepairOrderStatus::WaitingApproval))->toBeTrue()
        ->and($glance->operationalStatus)->toBe('Waiting Approval')
        ->and($glance->clockLabel)->toBe('6d')
        ->and($glance->attention)->toBe('attention')
        ->and($glance->whyLabel)->toBe('Waiting on decision · not sent')
        ->and($glance->nextLabel)->toBe('Send estimate')
        ->and($glance->moneyLabel)->toBe('$9,027')
        ->and($glance->moneyCaption)->toBe('pending')
        ->and($glance->ageLabel)->toBe('6d');

    Carbon::setTestNow();
});

test('vehicle identity pressure does not become a new repair order status', function () {
    Carbon::setTestNow('2026-09-10 12:00:00');

    $repairOrder = glanceRepairOrder(RepairOrderStatus::WaitingApproval);
    $glance = (new WorkboardCardGlanceProjection)->forCard(
        glanceTriageCard($repairOrder, 'Vehicle ID Needed'),
        'waiting_approval',
        glanceTotals(902_700),
        null,
        now()->subDays(6),
        false,
    );

    expect($glance->operationalStatus)->toBe('Waiting Approval')
        ->and($glance->waitingOnCustomerDecision)->toBeTrue()
        ->and($repairOrder->status->is(RepairOrderStatus::WaitingApproval))->toBeTrue();

    Carbon::setTestNow();
});

test('waiting approval uses estimate viewed wait not RO created age', function () {
    Carbon::setTestNow('2026-09-10 12:00:00');

    $repairOrder = glanceRepairOrder(RepairOrderStatus::WaitingApproval);
    $viewed = new CommunicationEvent;
    $viewed->forceFill([
        'event_type' => OperationalCommunicationType::EstimateViewed,
        'occurred_at' => now()->subDays(2),
    ]);
    $repairOrder->setRelation('communicationEvents', collect([$viewed]));

    $glance = (new WorkboardCardGlanceProjection)->forCard(
        glanceTriageCard($repairOrder, 'Estimate Viewed'),
        'waiting_approval',
        glanceTotals(902_700),
        null,
        now()->subDays(20),
        false,
    );

    expect($glance->whyLabel)->toBe('Waiting on decision · viewed')
        ->and($glance->operationalStatus)->toBe('Waiting Approval')
        ->and($glance->nextLabel)->toBe('Follow up')
        ->and($glance->clockLabel)->toBe('2d')
        ->and($glance->attention)->toBe('attention')
        ->and($glance->ageLabel)->toBe('2d')
        ->and($glance->ageLabel)->not->toBe('4w');

    Carbon::setTestNow();
});

test('pickup balance due is collect money not pending decision', function () {
    $repairOrder = glanceRepairOrder(RepairOrderStatus::ReadyPickup);
    $balance = new BalanceDueResult(
        hasIssuedInvoice: true,
        invoiceTotalCents: 75_000,
        depositsAppliedCents: 0,
        paymentsAppliedCents: 0,
        refundsAppliedCents: 0,
        adjustmentsCents: 0,
        creditsAppliedCents: 0,
        writeOffsCents: 0,
        balanceDueCents: 75_000,
        unappliedDepositsCents: 0,
        invoiceStatus: InvoiceStatus::Issued,
    );

    $glance = (new WorkboardCardGlanceProjection)->forCard(
        glanceTriageCard($repairOrder),
        'completed',
        glanceTotals(75_000),
        $balance,
        now()->subHours(5),
        false,
    );

    expect($glance->waitingOnCustomerDecision)->toBeFalse()
        ->and($glance->operationalStatus)->toBe('Ready for Pickup')
        ->and($glance->clockLabel)->toBe('5h')
        ->and($glance->whyLabel)->toBe('Ready for pickup')
        ->and($glance->nextLabel)->toBe('Collect balance')
        ->and($glance->moneyLabel)->toBe('$750')
        ->and($glance->moneyCaption)->toBe('due');
});

test('estimate viewed on shop-floor work is not a customer-decision status', function () {
    $repairOrder = glanceRepairOrder(RepairOrderStatus::InProgress);
    $viewed = new CommunicationEvent;
    $viewed->forceFill([
        'event_type' => OperationalCommunicationType::EstimateViewed,
        'occurred_at' => now()->subDays(2),
    ]);
    $repairOrder->setRelation('communicationEvents', collect([$viewed]));

    $glance = (new WorkboardCardGlanceProjection)->forCard(
        glanceTriageCard($repairOrder, 'Estimate Viewed', customerWaiting: true),
        'work_in_progress',
        glanceTotals(150_00),
        null,
        now()->subDays(7),
        false,
    );

    expect($repairOrder->status->is(RepairOrderStatus::InProgress))->toBeTrue()
        ->and($glance->waitingOnCustomerDecision)->toBeFalse()
        ->and($glance->operationalStatus)->toBe('In Progress')
        ->and($glance->whyLabel)->toBe('Work in progress')
        ->and($glance->nextLabel)->toBe('Follow up')
        ->and($glance->moneyLabel)->toBe('$150')
        ->and($glance->moneyCaption)->toBeNull();
});

test('deferred recommendation follow-up is why without changing RO status', function () {
    $repairOrder = glanceRepairOrder(RepairOrderStatus::InProgress);

    $glance = (new WorkboardCardGlanceProjection)->forCard(
        glanceTriageCard($repairOrder),
        'work_in_progress',
        glanceTotals(200_000),
        null,
        now()->subDays(1),
        true,
    );

    expect($repairOrder->status->is(RepairOrderStatus::InProgress))->toBeTrue()
        ->and($glance->operationalStatus)->toBe('In Progress')
        ->and($glance->clockLabel)->toBe('1d')
        ->and($glance->attention)->toBe('attention')
        ->and($glance->whyLabel)->toBe('Deferred work due')
        ->and($glance->nextLabel)->toBe('Present recommendation');
});

test('board money stays in place when the current ticket value is zero', function () {
    $repairOrder = glanceRepairOrder(RepairOrderStatus::Draft);

    $glance = (new WorkboardCardGlanceProjection)->forCard(
        glanceTriageCard($repairOrder),
        'estimates',
        glanceTotals(0),
        null,
        now()->subDay(),
        false,
    );

    expect($glance->moneyLabel)->toBe('$0')
        ->and($glance->moneyCaption)->toBeNull()
        ->and($glance->operationalStatus)->not->toBe('');
});
