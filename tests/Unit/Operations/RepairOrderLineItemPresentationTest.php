<?php

use App\Ark\Operations\RepairOrders\EstimateTotals;
use App\Ark\Operations\RepairOrders\PartProcurementState;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineItemPresentation;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use Tests\TestCase;


test('procurement chip tones map operational states', function () {
    expect(RepairOrderLineItemPresentation::procurementChipTone(PartProcurementState::Ordered))
        ->toBe('ordered')
        ->and(RepairOrderLineItemPresentation::procurementChipTone(PartProcurementState::None))
        ->toBe('needs-ordered');
});

test('profitability meter uses shop target thresholds', function () {
    $line = new RepairOrderLine([
        'type' => RepairOrderLineType::Part,
        'part_cost_cents' => 5400,
        'unit_price_cents' => 10000,
    ]);

    $meter = RepairOrderLineItemPresentation::profitabilityMeter($line);

    expect($meter)->not->toBeNull()
        ->and($meter['percent'])->toBe(46)
        ->and($meter['label'])->toBe('Thin');
});

test('matrix pricing chip surfaces matrix acceptance in view mode', function () {
    $line = new RepairOrderLine([
        'type' => RepairOrderLineType::Part,
        'pricing_matrix_name' => 'AFT Parts',
        'matrix_suggested_price_cents' => 36998,
        'matrix_applied' => true,
        'pricing_mode' => 'matrix',
    ]);

    expect(RepairOrderLineItemPresentation::matrixPricingChip($line))
        ->toBe(['label' => 'Matrix', 'variant' => 'matrix-accepted'])
        ->and(RepairOrderLineItemPresentation::viewContextLines($line))
        ->toBe([]);
});

test('view context lines stay empty when chips already carry the story', function () {
    $line = new RepairOrderLine([
        'type' => RepairOrderLineType::Part,
        'pricing_mode' => 'matrix',
        'part_cost_cents' => 10000,
        'unit_price_cents' => 15000,
        'part_number' => 'ABC123',
    ]);

    expect(RepairOrderLineItemPresentation::viewContextLines($line))->toBe([])
        ->and(RepairOrderLineItemPresentation::editContextLines($line))
        ->toBe(['Part # ABC123']);
});

test('edit pricing segments include matrix and markup detail', function () {
    $line = new RepairOrderLine([
        'type' => RepairOrderLineType::Part,
        'part_cost_cents' => 19999,
        'unit_price_cents' => 36998,
        'quantity' => '1.00',
        'pricing_matrix_name' => 'AFT Parts',
        'matrix_suggested_price_cents' => 36998,
        'matrix_applied' => true,
        'pricing_mode' => 'matrix',
    ]);

    $totals = new EstimateTotals(
        lines: collect([$line]),
        concernSubtotals: [],
        laborCents: 0,
        partsCents: 36998,
        feesCents: 0,
        taxCents: 0,
    );

    expect(RepairOrderLineItemPresentation::editPricingSegments($line, $totals))
        ->toBe(['AFT Parts accepted $369.98', 'Markup 85%']);
});

test('profitability inspect card exposes margin detail for hover inspection', function () {
    $line = new RepairOrderLine([
        'type' => RepairOrderLineType::Part,
        'part_cost_cents' => 19999,
        'unit_price_cents' => 36998,
        'quantity' => '1.00',
        'shop_fee_cents' => 261,
        'tax_cents' => 3052,
    ]);

    $totals = new EstimateTotals(
        lines: collect([$line]),
        concernSubtotals: [],
        laborCents: 0,
        partsCents: 36998,
        feesCents: 0,
        taxCents: 3052,
    );

    $card = RepairOrderLineItemPresentation::profitabilityInspectCard(
        $line,
        $totals,
        app(\App\Ark\Operations\Financial\EstimateTotalsCalculator::class),
    );

    expect($card)->not->toBeNull()
        ->and($card['title'])->toBe('Profitability')
        ->and(collect($card['items'])->pluck('label')->all())->toContain('Margin', 'Markup', 'Shop fee', 'Tax')
        ->and($card['footer'])->toContain('Below target');
});

test('matrix inspect card explains matrix decision', function () {
    $line = new RepairOrderLine([
        'type' => RepairOrderLineType::Part,
        'part_cost_cents' => 19999,
        'unit_price_cents' => 36998,
        'pricing_matrix_name' => 'AFT Parts',
        'matrix_suggested_price_cents' => 36998,
        'matrix_applied' => true,
        'pricing_mode' => 'matrix',
    ]);

    $totals = new EstimateTotals(
        lines: collect([$line]),
        concernSubtotals: [],
        laborCents: 0,
        partsCents: 36998,
        feesCents: 0,
        taxCents: 0,
    );

    $card = RepairOrderLineItemPresentation::matrixInspectCard($line, $totals);

    expect($card)->not->toBeNull()
        ->and(collect($card['items'])->firstWhere('label', 'Decision')['detail'])->toBe('Accepted');
});

test('procurement inspect card includes status timestamp and vendor context', function () {
    $line = new RepairOrderLine([
        'type' => RepairOrderLineType::Part,
        'procurement_state' => PartProcurementState::Ordered,
        'vendor_name' => 'NAPA',
    ]);
    $line->updated_at = now();

    $card = RepairOrderLineItemPresentation::procurementInspectCard($line);
    $labels = collect($card['items'])->pluck('label')->all();

    expect($labels)->toContain('Status updated', 'Vendor', 'Status', 'Next action');
});
