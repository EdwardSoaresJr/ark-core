<?php

use App\Ark\Operations\Financial\BalanceDueResult;
use App\Ark\Operations\Financial\InvoiceStatus;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Today\AdvisorHomeCardSurfaceProjection;
use App\Ark\Operations\Workboard\WorkboardTriageCard;

test('home card chip prefers balance due over lifecycle posture', function () {
    $repairOrder = new RepairOrder;
    $repairOrder->forceFill([
        'id' => 10,
        'repair_order_id' => 2533,
        'status' => RepairOrderStatus::ReadyPickup->value,
    ]);

    $card = new WorkboardTriageCard(
        repairOrder: $repairOrder,
        vehicleLabel: '2013 Dodge Journey',
        concernSummary: 'Brakes',
        concernHeadline: 'Brakes',
        signalLabel: null,
        signalTone: 'neutral',
        ageLabel: '2d',
        ageMinutes: 2880,
        pressureScore: 0,
        countsAsNeedsAttention: false,
        countsAsCustomerWaiting: false,
        countsAsUnassigned: false,
        countsAsOverduePickup: false,
        href: '/ro/10',
    );

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

    $method = new ReflectionMethod(AdvisorHomeCardSurfaceProjection::class, 'resolveChip');
    $method->setAccessible(true);

    $chip = $method->invoke(
        app(AdvisorHomeCardSurfaceProjection::class),
        $repairOrder,
        $card,
        $balance,
    );

    expect($chip->label)->toBe('Balance Due')
        ->and($chip->tone)->toBe('alert');
});

test('home card next move follows up waiting approval without repeating age or chip', function () {
    $repairOrder = new RepairOrder;
    $repairOrder->forceFill([
        'id' => 11,
        'repair_order_id' => 2534,
        'status' => RepairOrderStatus::WaitingApproval->value,
    ]);

    $card = new WorkboardTriageCard(
        repairOrder: $repairOrder,
        vehicleLabel: '2018 Ram 2500',
        concernSummary: 'Brake noise when stopping.',
        concernHeadline: 'Brake noise when stopping',
        signalLabel: 'Estimate Viewed',
        signalTone: 'warn',
        ageLabel: '4d',
        ageMinutes: 5760,
        pressureScore: 12,
        countsAsNeedsAttention: true,
        countsAsCustomerWaiting: false,
        countsAsUnassigned: false,
        countsAsOverduePickup: false,
        href: '/ro/11',
    );

    expect($card->nextMoveLabel('Waiting Approval'))->toBe('Follow up')
        ->and($card->nextMoveLabel('Follow up'))->toBeNull();
});
