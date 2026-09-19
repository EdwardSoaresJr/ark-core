<?php

use App\Ark\Operations\Financial\BalanceDueResult;
use App\Ark\Operations\Financial\InvoiceStatus;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Today\AdvisorHomeCardSurfaceProjection;
use App\Ark\Operations\Workboard\WorkboardCardGlance;
use App\Ark\Operations\Workboard\WorkboardTriageCard;

test('home card chip uses configured status not balance due overlay', function () {
    $repairOrder = new RepairOrder;
    $repairOrder->forceFill([
        'id' => 10,
        'repair_order_id' => 2533,
        'status' => RepairOrderStatus::ReadyPickup->value,
    ]);

    $glance = new WorkboardCardGlance(
        whyLabel: 'Ready for pickup',
        nextLabel: 'Collect balance',
        moneyLabel: '$750',
        moneyCaption: 'due',
        ageLabel: '5h',
        waitingOnCustomerDecision: false,
        operationalStatus: 'Ready for Pickup',
        clockLabel: '5h',
        attention: 'normal',
        configuredStatusColor: 'success',
    );

    $method = new ReflectionMethod(AdvisorHomeCardSurfaceProjection::class, 'resolveChip');
    $method->setAccessible(true);

    $chip = $method->invoke(
        app(AdvisorHomeCardSurfaceProjection::class),
        $repairOrder,
        $glance,
    );

    expect($chip->label)->toBe('Ready for Pickup')
        ->and($chip->tone)->toBe('quiet')
        ->and($chip->statusColor)->toBe('success');
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
