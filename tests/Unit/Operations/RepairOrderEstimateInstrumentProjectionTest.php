<?php

use App\Ark\Operations\RepairOrders\EstimateTotals;
use App\Ark\Operations\RepairOrders\PartProcurementState;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderEstimateInstrumentProjection;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use Tests\TestCase;


test('estimate instrument strip exposes profitability and procurement gauges', function () {
    $concern = new RepairOrderConcern([
        'disposition' => RepairOrderConcernDisposition::Approved,
        'summary' => 'Brakes',
    ]);
    $concern->id = 11;

    $partLine = new RepairOrderLine([
        'type' => RepairOrderLineType::Part,
        'repair_order_concern_id' => 11,
        'description' => 'Pads',
        'quantity' => '1.00',
        'part_cost_cents' => 5000,
        'unit_price_cents' => 10000,
        'subtotal_cents' => 10000,
        'total_cents' => 10000,
        'procurement_state' => PartProcurementState::Ordered,
    ]);
    $partLine->id = 1;
    $partLine->setRelation('concern', $concern);

    $laborLine = new RepairOrderLine([
        'type' => RepairOrderLineType::Labor,
        'repair_order_concern_id' => 11,
        'description' => 'Labor',
        'quantity' => '1.00',
        'labor_billed_hours' => '1.50',
        'unit_price_cents' => 15000,
        'subtotal_cents' => 22500,
        'total_cents' => 22500,
    ]);
    $laborLine->id = 2;
    $laborLine->setRelation('concern', $concern);

    $repairOrder = new RepairOrder;
    $repairOrder->repair_order_id = 42;
    $repairOrder->setRelation('concerns', collect([$concern]));
    $repairOrder->setRelation('lines', collect([$partLine, $laborLine]));
    $repairOrder->setRelation('assignedTechnician', null);

    $totals = new EstimateTotals(
        lines: collect([$partLine, $laborLine]),
        concernSubtotals: [11 => 32500],
        laborCents: 22500,
        partsCents: 10000,
        feesCents: 0,
        taxCents: 0,
        grossLaborCents: 22500,
        grossPartsCents: 10000,
    );

    $projection = RepairOrderEstimateInstrumentProjection::for($repairOrder, $totals);
    $keys = collect($projection['instruments'])->pluck('key')->all();

    expect($keys)->toBe(['total', 'margin', 'parts', 'labor', 'approval'])
        ->and(collect($projection['instruments'])->firstWhere('key', 'parts')['value'])->toBe('Ordered 1/1')
        ->and(collect($projection['instruments'])->firstWhere('key', 'approval')['value'])->toBe('100%');

    $totalInspect = collect($projection['instruments'])->firstWhere('key', 'total')['inspect'];
    $marginInspect = collect($projection['instruments'])->firstWhere('key', 'margin')['inspect'];
    $totalLabels = collect($totalInspect['items'])->pluck('label')->all();
    $marginLabels = collect($marginInspect['items'])->pluck('label')->all();

    expect($totalInspect['title'])->toBe('Estimate total')
        ->and($totalLabels)->toContain('Labor', 'Parts', 'Subtotal', 'Customer total')
        ->and($totalLabels)->not->toContain('Parts GP', 'Effective labor rate', 'Warranty exposure')
        ->and($marginInspect['title'])->toBe('Repair order profitability')
        ->and($marginLabels)->toContain('Parts GP', 'Labor GP', 'Effective labor rate', 'Profit / hour', 'Warranty exposure')
        ->and($marginLabels)->not->toContain('Customer total', 'Tax');
});
