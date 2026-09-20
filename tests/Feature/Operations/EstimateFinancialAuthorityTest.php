<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\RepairOrders\ConcernBillingPosture;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLinePricing;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;

test('line totals round deterministically at the line level', function () {
    $calculator = app(EstimateTotalsCalculator::class);

    expect($calculator->unitPriceCents('33.335'))->toBe(3334)
        ->and($calculator->lineTotalCents('3.333', 1999))->toBe(6663)
        ->and($calculator->lineTotalCents('2.675', 100))->toBe(268);
});

test('parts matrix suggestions use markup as pricing authority and calculate margin separately', function () {
    $settings = ShopSettings::current();
    $settings->update([
        'parts_matrices' => [
            [
                'key' => 'test-parts',
                'name' => 'Test Parts',
                'is_default' => true,
                'rows' => [
                    [
                        'min_cost' => '0.00',
                        'max_cost' => '99999.99',
                        'markup_percentage' => '100.00',
                        'margin_percentage' => '10.00',
                        'sort_order' => 1,
                    ],
                ],
            ],
        ],
    ]);

    $calculator = app(EstimateTotalsCalculator::class);

    expect($calculator->matrixSuggestedPriceCents(2000, $settings))->toBe(4000)
        ->and($settings->defaultPartsMatrix()['rows'][0]['margin_percentage'])->toBe('50');
});

test('grouped subtotals and estimate totals derive from persisted line cents', function () {
    ShopSettings::current()->update(['shop_fee_enabled' => false, 'tax_enabled' => false]);
    $calculator = app(EstimateTotalsCalculator::class);
    $repairOrder = repairOrderForFinancialAuthority();

    $brakes = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Brake vibration',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'recommendation_intent' => 'immediate_attention',
        'position' => 1,
    ]);

    $cooling = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Coolant smell',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'recommendation_intent' => 'maintenance',
        'position' => 2,
    ]);

    $repairOrder->lines()->create([
        'repair_order_concern_id' => $brakes->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Brake inspection',
        'quantity' => '1.00',
        'unit_price_cents' => 15000,
        'subtotal_cents' => 15000,
    ]);

    $repairOrder->lines()->create([
        'repair_order_concern_id' => $brakes->id,
        'type' => RepairOrderLineType::Part,
        'description' => 'Brake pads',
        'quantity' => '1.00',
        'unit_price_cents' => 8750,
        'subtotal_cents' => 8750,
    ]);

    $repairOrder->lines()->create([
        'repair_order_concern_id' => $cooling->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Pressure test',
        'quantity' => '0.60',
        'unit_price_cents' => 15000,
        'subtotal_cents' => 9000,
    ]);

    $repairOrder->lines()->create([
        'repair_order_concern_id' => $cooling->id,
        'type' => RepairOrderLineType::Fee,
        'description' => 'Shop supplies',
        'quantity' => '1.00',
        'unit_price_cents' => 1800,
        'subtotal_cents' => 1800,
    ]);

    $calculator->recalculateRepairOrder($repairOrder);
    $totals = $calculator->totalsFor($repairOrder);

    expect($totals->concernSubtotalCents($brakes->id))->toBe(23750)
        ->and($totals->concernSubtotalCents($cooling->id))->toBe(10800)
        ->and($totals->laborCents())->toBe(24000)
        ->and($totals->partsCents())->toBe(8750)
        ->and($totals->feesCents())->toBe(1800)
        ->and($totals->taxCents())->toBe(0)
        ->and($totals->totalCents())->toBe(34550);
});

test('deferred concerns stay visible but do not count toward estimate total', function () {
    ShopSettings::current()->update(['shop_fee_enabled' => false, 'tax_enabled' => false]);
    $calculator = app(EstimateTotalsCalculator::class);
    $repairOrder = repairOrderForFinancialAuthority();

    $approved = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Approved brakes',
        'disposition' => RepairOrderConcernDisposition::Approved,
        'recommendation_intent' => 'maintenance',
        'position' => 1,
    ]);

    $deferred = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Deferred alignment',
        'disposition' => RepairOrderConcernDisposition::Deferred,
        'recommendation_intent' => 'maintenance',
        'position' => 2,
    ]);

    $repairOrder->lines()->create([
        'repair_order_concern_id' => $approved->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Brake job',
        'quantity' => '1.00',
        'unit_price_cents' => 15000,
        'subtotal_cents' => 15000,
        'total_cents' => 15000,
    ]);

    $repairOrder->lines()->create([
        'repair_order_concern_id' => $deferred->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Alignment next visit',
        'quantity' => '1.00',
        'unit_price_cents' => 26000,
        'subtotal_cents' => 26000,
        'total_cents' => 26000,
    ]);

    $calculator->recalculateRepairOrder($repairOrder);
    $totals = $calculator->totalsFor($repairOrder);

    expect($totals->concernSubtotalCents($approved->id))->toBe(15000)
        ->and($totals->concernSubtotalCents($deferred->id))->toBe(26000)
        ->and($totals->totalCents())->toBe(15000)
        ->and($repairOrder->fresh()->futureWorkSubtotalCents())->toBe(26000);
});

test('recommended work counts toward total only until approved work exists on the repair order', function () {
    ShopSettings::current()->update(['shop_fee_enabled' => false, 'tax_enabled' => false]);
    $calculator = app(EstimateTotalsCalculator::class);
    $repairOrder = repairOrderForFinancialAuthority();

    $recommended = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Recommended suspension',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'recommendation_intent' => 'maintenance',
        'position' => 1,
    ]);

    $approved = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Approved brakes',
        'disposition' => RepairOrderConcernDisposition::Approved,
        'recommendation_intent' => 'maintenance',
        'position' => 2,
    ]);

    $repairOrder->lines()->create([
        'repair_order_concern_id' => $recommended->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Strut recommendation',
        'quantity' => '1.00',
        'unit_price_cents' => 42000,
        'subtotal_cents' => 42000,
        'total_cents' => 42000,
    ]);

    $repairOrder->lines()->create([
        'repair_order_concern_id' => $approved->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Brake job',
        'quantity' => '1.00',
        'unit_price_cents' => 15000,
        'subtotal_cents' => 15000,
        'total_cents' => 15000,
    ]);

    $calculator->recalculateRepairOrder($repairOrder);
    $totals = $calculator->totalsFor($repairOrder);

    expect($totals->concernSubtotalCents($recommended->id))->toBe(42000)
        ->and($totals->concernSubtotalCents($approved->id))->toBe(15000)
        ->and($totals->totalCents())->toBe(15000);

    $recommendedOnly = repairOrderForFinancialAuthority();
    $onlyRecommended = RepairOrderConcern::query()->create([
        'repair_order_id' => $recommendedOnly->id,
        'summary' => 'Awaiting authorization',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'recommendation_intent' => 'maintenance',
        'position' => 1,
    ]);

    $recommendedOnly->lines()->create([
        'repair_order_concern_id' => $onlyRecommended->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Presented work',
        'quantity' => '1.00',
        'unit_price_cents' => 9900,
        'subtotal_cents' => 9900,
        'total_cents' => 9900,
    ]);

    $calculator->recalculateRepairOrder($recommendedOnly);
    $presentedTotals = $calculator->totalsFor($recommendedOnly);

    expect($presentedTotals->totalCents())->toBe(9900);
});

test('draft scope work does not count toward estimate totals', function () {
    ShopSettings::current()->update(['shop_fee_enabled' => false, 'tax_enabled' => false]);
    $calculator = app(EstimateTotalsCalculator::class);
    $repairOrder = repairOrderForFinancialAuthority();

    $draft = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Draft suspension scope',
        'disposition' => RepairOrderConcernDisposition::Draft,
        'recommendation_intent' => 'maintenance',
        'position' => 1,
    ]);

    $repairOrder->lines()->create([
        'repair_order_concern_id' => $draft->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Strut research',
        'quantity' => '1.00',
        'unit_price_cents' => 42000,
        'subtotal_cents' => 42000,
        'total_cents' => 42000,
    ]);

    $calculator->recalculateRepairOrder($repairOrder);
    $totals = $calculator->totalsFor($repairOrder);

    expect($totals->totalCents())->toBe(0)
        ->and($totals->concernSubtotalCents($draft->id))->toBe(0);
});

test('draft concern header stays at zero when approved work exists on the repair order', function () {
    ShopSettings::current()->update(['shop_fee_enabled' => false, 'tax_enabled' => false]);
    $calculator = app(EstimateTotalsCalculator::class);
    $repairOrder = repairOrderForFinancialAuthority();

    $approved = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Approved brakes',
        'disposition' => RepairOrderConcernDisposition::Approved,
        'recommendation_intent' => 'maintenance',
        'position' => 1,
    ]);

    $draft = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Draft alternator',
        'disposition' => RepairOrderConcernDisposition::Draft,
        'recommendation_intent' => 'maintenance',
        'position' => 2,
    ]);

    $repairOrder->lines()->create([
        'repair_order_concern_id' => $approved->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Brake job',
        'quantity' => '1.00',
        'unit_price_cents' => 15000,
        'subtotal_cents' => 15000,
        'total_cents' => 15000,
    ]);

    $repairOrder->lines()->create([
        'repair_order_concern_id' => $draft->id,
        'type' => RepairOrderLineType::Part,
        'description' => 'Alternator',
        'quantity' => '1.00',
        'unit_price_cents' => 71108,
        'subtotal_cents' => 71108,
        'total_cents' => 71108,
    ]);

    $calculator->recalculateRepairOrder($repairOrder);
    $totals = $calculator->totalsFor($repairOrder);

    expect($totals->totalCents())->toBe(15000)
        ->and($totals->concernSubtotalCents($approved->id))->toBe(15000)
        ->and($totals->concernSubtotalCents($draft->id))->toBe(0);
});

test('note lines do not contribute to concern subtotals or estimate totals', function () {
    ShopSettings::current()->update(['shop_fee_enabled' => false, 'tax_enabled' => false]);
    $calculator = app(EstimateTotalsCalculator::class);
    $repairOrder = repairOrderForFinancialAuthority();
    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Diagnostic authorization',
        'recommendation_intent' => 'immediate_attention',
        'position' => 1,
    ]);

    $repairOrder->lines()->create([
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Note,
        'description' => 'Call customer before exceeding diagnostic authorization',
        'quantity' => '1.00',
        'unit_price_cents' => 999999,
        'subtotal_cents' => 999999,
    ]);

    $calculator->recalculateRepairOrder($repairOrder);
    $totals = $calculator->totalsFor($repairOrder);

    expect($totals->concernSubtotalCents($concern->id))->toBe(0)
        ->and($totals->totalCents())->toBe(0);
});

test('repeated recalculation is stable for the same authoritative line cents', function () {
    ShopSettings::current()->update(['shop_fee_enabled' => false, 'tax_enabled' => false]);
    $calculator = app(EstimateTotalsCalculator::class);
    $repairOrder = repairOrderForFinancialAuthority();
    $concern = concernForFinancialAuthority($repairOrder);

    $repairOrder->lines()->create([
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Diagnostic',
        'quantity' => '1.333',
        'unit_price_cents' => 15000,
        'subtotal_cents' => $calculator->lineTotalCents('1.333', 15000),
    ]);

    $calculator->recalculateRepairOrder($repairOrder);
    $first = $calculator->totalsFor($repairOrder->fresh());
    $second = $calculator->totalsFor($repairOrder->fresh());

    expect($first->totalCents())->toBe(19950)
        ->and($second->totalCents())->toBe(19950)
        ->and($second->totalCents())->toBe($first->totalCents());
});

test('tax and shop fees are persisted at the line level', function () {
    $settings = ShopSettings::current();
    $settings->update([
        'tax_enabled' => true,
        'default_tax_rate' => '8.250',
        'taxable_labor' => false,
        'taxable_parts' => true,
        'shop_fee_enabled' => false,
    ]);

    $calculator = app(EstimateTotalsCalculator::class);
    $repairOrder = repairOrderForFinancialAuthority();
    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Line-level financials',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'recommendation_intent' => 'maintenance',
        'position' => 1,
    ]);

    $labor = $repairOrder->lines()->create([
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Diagnostic labor',
        'quantity' => '1.00',
        'unit_price_cents' => 10000,
        'subtotal_cents' => 10000,
    ]);

    $part = $repairOrder->lines()->create([
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Part,
        'description' => 'Sensor',
        'quantity' => '1.00',
        'unit_price_cents' => 30000,
        'subtotal_cents' => 30000,
    ]);

    $note = $repairOrder->lines()->create([
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Note,
        'description' => 'Deferred note',
        'quantity' => '1.00',
        'unit_price_cents' => 50000,
        'subtotal_cents' => 0,
    ]);

    $calculator->recalculateRepairOrder($repairOrder);
    $totals = $calculator->totalsFor($repairOrder->fresh());

    expect($labor->fresh())
        ->subtotal_cents->toBe(10000)
        ->tax_cents->toBe(0)
        ->shop_fee_cents->toBe(0)
        ->total_cents->toBe(10000)
        ->and($part->fresh())
        ->subtotal_cents->toBe(30000)
        ->tax_cents->toBe(2475)
        ->shop_fee_cents->toBe(0)
        ->total_cents->toBe(32475)
        ->and($note->fresh())
        ->subtotal_cents->toBe(0)
        ->tax_cents->toBe(0)
        ->shop_fee_cents->toBe(0)
        ->total_cents->toBe(0)
        ->and($totals->taxCents())->toBe(2475)
        ->and($totals->totalCents())->toBe(42475);
});

test('allocated shop fee rollup lines are removed when shop fees are enabled', function () {
    ShopSettings::current()->update([
        'tax_enabled' => false,
        'shop_fee_enabled' => true,
        'shop_fee_rate' => '5.000',
        'shop_fee_cap_cents' => null,
    ]);

    $calculator = app(EstimateTotalsCalculator::class);
    $repairOrder = repairOrderForFinancialAuthority();
    $concern = concernForFinancialAuthority($repairOrder);

    $repairOrder->lines()->create([
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Brake inspection',
        'quantity' => '1.00',
        'unit_price_cents' => 10000,
    ]);

    $rollup = $repairOrder->lines()->create([
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Fee,
        'description' => 'Shop supplies',
        'quantity' => '1.00',
        'unit_price_cents' => 1195,
    ]);

    $calculator->recalculateRepairOrder($repairOrder);

    expect(RepairOrderLine::query()->whereKey($rollup->id)->exists())->toBeFalse()
        ->and($repairOrder->fresh()->lines)->toHaveCount(1)
        ->and($repairOrder->fresh()->lines->first()->shop_fee_cents)->toBe(500)
        ->and($calculator->totalsFor($repairOrder->fresh())->feesCents())->toBe(500);
});

test('sublet lines do not receive allocated shop fees', function () {
    ShopSettings::current()->update([
        'tax_enabled' => false,
        'shop_fee_enabled' => true,
        'shop_fee_rate' => '5.000',
        'shop_fee_cap_cents' => null,
    ]);

    $calculator = app(EstimateTotalsCalculator::class);
    $repairOrder = repairOrderForFinancialAuthority();
    $concern = concernForFinancialAuthority($repairOrder);

    $labor = $repairOrder->lines()->create([
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Brake inspection',
        'quantity' => '1.00',
        'unit_price_cents' => 10000,
    ]);

    $sublet = $repairOrder->lines()->create([
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Sublet,
        'description' => 'Alignment at tire shop',
        'quantity' => '1.00',
        'unit_price_cents' => 8000,
    ]);

    $calculator->recalculateRepairOrder($repairOrder);

    $labor = $labor->fresh();
    $sublet = $sublet->fresh();

    expect($labor->shop_fee_cents)->toBe(500)
        ->and($sublet->shop_fee_cents)->toBe(0)
        ->and($calculator->totalsFor($repairOrder->fresh())->feesCents())->toBe(500);
});

test('sublet pricing stores vendor cost and sell price', function () {
    $pricing = app(RepairOrderLinePricing::class);
    $repairOrder = repairOrderForFinancialAuthority();

    $attributes = $pricing->attributesFor([
        'type' => RepairOrderLineType::Sublet->value,
        'description' => 'Alignment sublet',
        'quantity' => '1.00',
        'part_cost' => '75.00',
        'unit_price' => '89.00',
    ], $repairOrder);

    expect($attributes['part_cost_cents'])->toBe(7500)
        ->and($attributes['unit_price_cents'])->toBe(8900)
        ->and($attributes['pricing_mode'])->toBe('manual');
});

test('shop fees are not taxed unless taxable shop fees is enabled', function () {
    ShopSettings::current()->update([
        'tax_enabled' => true,
        'default_tax_rate' => '10.000',
        'taxable_labor' => false,
        'taxable_parts' => true,
        'taxable_shop_fees' => false,
        'shop_fee_enabled' => true,
        'shop_fee_rate' => '10.000',
        'shop_fee_cap_cents' => null,
    ]);

    $calculator = app(EstimateTotalsCalculator::class);
    $repairOrder = repairOrderForFinancialAuthority();
    $concern = concernForFinancialAuthority($repairOrder);

    $part = $repairOrder->lines()->create([
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Part,
        'description' => 'Sensor',
        'quantity' => '1.00',
        'unit_price_cents' => 10000,
    ]);

    $calculator->recalculateRepairOrder($repairOrder);
    $part = $part->fresh();

    expect($part->subtotal_cents)->toBe(10000)
        ->and($part->shop_fee_cents)->toBe(1000)
        ->and($part->tax_cents)->toBe(1000)
        ->and($part->total_cents)->toBe(12000);
});

test('shop fees are taxed when taxable shop fees is enabled', function () {
    ShopSettings::current()->update([
        'tax_enabled' => true,
        'default_tax_rate' => '10.000',
        'taxable_labor' => false,
        'taxable_parts' => true,
        'taxable_shop_fees' => true,
        'shop_fee_enabled' => true,
        'shop_fee_rate' => '10.000',
        'shop_fee_cap_cents' => null,
    ]);

    $calculator = app(EstimateTotalsCalculator::class);
    $repairOrder = repairOrderForFinancialAuthority();
    $concern = concernForFinancialAuthority($repairOrder);

    $part = $repairOrder->lines()->create([
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Part,
        'description' => 'Sensor',
        'quantity' => '1.00',
        'unit_price_cents' => 10000,
    ]);

    $calculator->recalculateRepairOrder($repairOrder);
    $part = $part->fresh();

    expect($part->subtotal_cents)->toBe(10000)
        ->and($part->shop_fee_cents)->toBe(1000)
        ->and($part->tax_cents)->toBe(1100)
        ->and($part->total_cents)->toBe(12100);
});

test('warranty billing posture has no shop hazmat fees when shop fees are enabled globally', function () {
    ShopSettings::current()->update([
        'tax_enabled' => false,
        'shop_fee_enabled' => true,
        'shop_fee_rate' => '3.500',
        'shop_fee_cap_cents' => null,
        'customer_types' => ShopSettings::DEFAULT_CUSTOMER_TYPES,
    ]);

    $calculator = app(EstimateTotalsCalculator::class);
    $repairOrder = repairOrderForFinancialAuthority();
    $concern = concernForFinancialAuthority($repairOrder);
    $concern->update(['billing_posture' => ConcernBillingPosture::Warranty]);

    $part = $repairOrder->lines()->create([
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Part,
        'description' => 'Warranty-covered part',
        'quantity' => '1.00',
        'unit_price_cents' => 10000,
    ]);

    $calculator->recalculateRepairOrder($repairOrder->fresh(['concerns', 'lines.concern']));

    expect($part->fresh()->shop_fee_cents)->toBe(0)
        ->and($calculator->totalsFor($repairOrder->fresh(['concerns', 'lines.concern']))->allocatedShopFeesCents())->toBe(0);
});

test('comeback manual part lines keep zero sell when cost is entered', function () {
    ShopSettings::current()->update(['shop_fee_enabled' => false, 'tax_enabled' => false]);
    $calculator = app(EstimateTotalsCalculator::class);
    $pricing = app(RepairOrderLinePricing::class);
    $repairOrder = repairOrderForFinancialAuthority();
    $concern = concernForFinancialAuthority($repairOrder);
    $concern->update(['billing_posture' => ConcernBillingPosture::Comeback]);

    $preview = $pricing->previewFor([
        'type' => RepairOrderLineType::Part->value,
        'repair_order_concern_id' => $concern->id,
        'part_cost' => '24.62',
        'unit_price' => '0',
        'pricing_mode' => 'manual',
    ], $repairOrder->fresh(['concerns', 'customer']));

    expect($preview['pricing_mode'])->toBe('manual')
        ->and($preview['part_cost_cents'])->toBe(2462)
        ->and($preview['unit_price_cents'])->toBe(0);

    $part = $repairOrder->lines()->create([
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Part,
        'description' => 'Power steering fluid',
        'quantity' => '1.00',
        'part_cost_cents' => 2462,
        'unit_price_cents' => 0,
        'pricing_mode' => 'manual',
        'pricing_matrix_key' => 'warranty-no-markup',
    ]);

    $calculator->recalculateRepairOrder($repairOrder->fresh(['concerns', 'lines.concern']));
    $part->refresh();

    expect($part->unit_price_cents)->toBe(0)
        ->and($part->part_cost_cents)->toBe(2462)
        ->and($calculator->totalsFor($repairOrder->fresh(['concerns', 'lines.concern']))->partsCents())->toBe(0);
});

test('fleet billing posture can override shop fee rate', function () {
    ShopSettings::current()->update([
        'tax_enabled' => false,
        'shop_fee_enabled' => true,
        'shop_fee_rate' => '5.000',
        'customer_types' => [
            ['name' => 'Retail', 'shop_fees_enabled' => true, 'shop_fee_rate_override' => null, 'discount_type' => 'none', 'discount_amount' => null, 'default_parts_matrix_key' => null],
            ['name' => 'Fleet', 'shop_fees_enabled' => true, 'shop_fee_rate_override' => '2.500', 'discount_type' => 'none', 'discount_amount' => null, 'default_parts_matrix_key' => null],
        ],
    ]);

    $calculator = app(EstimateTotalsCalculator::class);
    $repairOrder = repairOrderForFinancialAuthority();
    $concern = concernForFinancialAuthority($repairOrder);
    $concern->update(['billing_posture' => ConcernBillingPosture::Fleet]);

    $part = $repairOrder->lines()->create([
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Part,
        'description' => 'Fleet part',
        'quantity' => '1.00',
        'unit_price_cents' => 10000,
    ]);

    $calculator->recalculateRepairOrder($repairOrder->fresh(['customer']));

    expect($part->fresh()->shop_fee_cents)->toBe(250);
});

test('shop supplies fees are allocated onto eligible persisted lines', function () {
    $settings = ShopSettings::current();
    $settings->update([
        'tax_enabled' => false,
        'shop_fee_enabled' => true,
        'shop_fee_rate' => '10.000',
        'shop_fee_cap_cents' => 1000,
    ]);

    $calculator = app(EstimateTotalsCalculator::class);
    $repairOrder = repairOrderForFinancialAuthority();
    $concern = concernForFinancialAuthority($repairOrder);

    $labor = $repairOrder->lines()->create([
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Diagnostic labor',
        'quantity' => '1.00',
        'unit_price_cents' => 10000,
    ]);

    $part = $repairOrder->lines()->create([
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Part,
        'description' => 'Sensor',
        'quantity' => '1.00',
        'unit_price_cents' => 30000,
    ]);

    $calculator->recalculateRepairOrder($repairOrder);

    $totals = $calculator->totalsFor($repairOrder->fresh());

    expect($labor->fresh()->shop_fee_cents)->toBe(250)
        ->and($labor->fresh()->total_cents)->toBe(10250)
        ->and($part->fresh()->shop_fee_cents)->toBe(750)
        ->and($part->fresh()->total_cents)->toBe(30750)
        ->and($totals->feesCents())->toBe(1000)
        ->and($totals->totalCents())->toBe(41000);
});

test('concern billing posture can collect fees on customer pay scopes while warranty scopes waive fees', function () {
    ShopSettings::current()->update([
        'tax_enabled' => false,
        'shop_fee_enabled' => true,
        'shop_fee_rate' => '10.000',
        'shop_fee_cap_cents' => null,
    ]);

    $calculator = app(EstimateTotalsCalculator::class);
    $repairOrder = repairOrderForFinancialAuthority();

    $customerPay = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Customer deductible and parts',
        'disposition' => RepairOrderConcernDisposition::Approved,
        'billing_posture' => ConcernBillingPosture::CustomerPay,
        'recommendation_intent' => 'maintenance',
        'position' => 1,
    ]);

    $warrantyPay = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Warranty labor',
        'disposition' => RepairOrderConcernDisposition::Approved,
        'billing_posture' => ConcernBillingPosture::Warranty,
        'recommendation_intent' => 'maintenance',
        'position' => 2,
    ]);

    $customerPart = $repairOrder->lines()->create([
        'repair_order_concern_id' => $customerPay->id,
        'type' => RepairOrderLineType::Part,
        'description' => 'Customer-paid part',
        'quantity' => '1.00',
        'unit_price_cents' => 100000,
    ]);

    $warrantyLabor = $repairOrder->lines()->create([
        'repair_order_concern_id' => $warrantyPay->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Warranty labor',
        'quantity' => '1.00',
        'unit_price_cents' => 50000,
    ]);

    $calculator->recalculateRepairOrder($repairOrder->fresh(['customer', 'concerns', 'lines.concern']));

    expect($customerPart->fresh()->shop_fee_cents)->toBe(10000)
        ->and($warrantyLabor->fresh()->shop_fee_cents)->toBe(0)
        ->and($calculator->totalsFor($repairOrder->fresh(['concerns', 'lines.concern']))->feesCents())->toBe(10000);
});

test('concern warranty billing posture suppresses fees even for retail customers', function () {
    ShopSettings::current()->update([
        'tax_enabled' => false,
        'shop_fee_enabled' => true,
        'shop_fee_rate' => '10.000',
        'shop_fee_cap_cents' => null,
    ]);

    $calculator = app(EstimateTotalsCalculator::class);
    $repairOrder = repairOrderForFinancialAuthority();
    $concern = concernForFinancialAuthority($repairOrder);
    $concern->update([
        'disposition' => RepairOrderConcernDisposition::Approved,
        'billing_posture' => ConcernBillingPosture::Warranty,
    ]);

    $part = $repairOrder->lines()->create([
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Part,
        'description' => 'Warranty-covered part',
        'quantity' => '1.00',
        'unit_price_cents' => 10000,
    ]);

    $calculator->recalculateRepairOrder($repairOrder->fresh(['customer', 'concerns', 'lines.concern']));

    expect($part->fresh()->shop_fee_cents)->toBe(0);
});

test('military billing class applies standing labor discount on non warranty scopes', function () {
    ShopSettings::current()->update([
        'tax_enabled' => false,
        'shop_fee_enabled' => false,
        'customer_types' => ShopSettings::DEFAULT_CUSTOMER_TYPES,
    ]);

    $calculator = app(EstimateTotalsCalculator::class);
    $repairOrder = repairOrderForFinancialAuthority();
    $repairOrder->customer->update(['customer_type' => 'Military']);
    $concern = concernForFinancialAuthority($repairOrder);

    $labor = $repairOrder->lines()->create([
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Brake labor',
        'quantity' => '1.00',
        'unit_price_cents' => 10000,
    ]);

    $calculator->recalculateRepairOrder($repairOrder->fresh(['customer', 'concerns', 'lines.concern']));
    $labor = $labor->fresh();

    expect($labor->standing_discount_cents)->toBe(1000)
        ->and($labor->total_cents)->toBe(9000)
        ->and($calculator->totalsFor($repairOrder->fresh(['customer', 'concerns', 'lines.concern']))->standingDiscountCents())->toBe(1000);
});

test('standing discount does not apply on warranty billing posture scopes', function () {
    ShopSettings::current()->update([
        'tax_enabled' => false,
        'shop_fee_enabled' => false,
        'customer_types' => ShopSettings::DEFAULT_CUSTOMER_TYPES,
    ]);

    $calculator = app(EstimateTotalsCalculator::class);
    $repairOrder = repairOrderForFinancialAuthority();
    $repairOrder->customer->update(['customer_type' => 'Military']);
    $concern = concernForFinancialAuthority($repairOrder);
    $concern->update(['billing_posture' => ConcernBillingPosture::Warranty]);

    $labor = $repairOrder->lines()->create([
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Warranty labor',
        'quantity' => '1.00',
        'unit_price_cents' => 10000,
    ]);

    $calculator->recalculateRepairOrder($repairOrder->fresh(['customer', 'concerns', 'lines.concern']));

    expect($labor->fresh()->standing_discount_cents)->toBe(0)
        ->and($labor->fresh()->total_cents)->toBe(10000);
});

test('standing discount reduces shop fee and tax base', function () {
    ShopSettings::current()->update([
        'tax_enabled' => true,
        'taxable_labor' => true,
        'taxable_parts' => false,
        'taxable_shop_fees' => true,
        'default_tax_rate' => '10.000',
        'shop_fee_enabled' => true,
        'shop_fee_rate' => '10.000',
        'customer_types' => ShopSettings::DEFAULT_CUSTOMER_TYPES,
    ]);

    $calculator = app(EstimateTotalsCalculator::class);
    $repairOrder = repairOrderForFinancialAuthority();
    $repairOrder->customer->update(['customer_type' => 'Military']);
    $concern = concernForFinancialAuthority($repairOrder);

    $labor = $repairOrder->lines()->create([
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Discounted labor',
        'quantity' => '1.00',
        'unit_price_cents' => 10000,
    ]);

    $calculator->recalculateRepairOrder($repairOrder->fresh(['customer', 'concerns', 'lines.concern']));
    $labor = $labor->fresh();

    expect($labor->standing_discount_cents)->toBe(1000)
        ->and($labor->shop_fee_cents)->toBe(900)
        ->and($labor->tax_cents)->toBe(990)
        ->and($labor->total_cents)->toBe(10890);
});
