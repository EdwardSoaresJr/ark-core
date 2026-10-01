<?php

use App\Ark\Operations\EstimatePricing\LaborPolicy;
use App\Ark\Operations\EstimatePricing\LaborRateType;
use App\Ark\Operations\EstimatePricing\OperationClass;
use App\Ark\Operations\EstimatePricing\UpsertLaborPolicyAction;
use App\Ark\Operations\Labor\LaborAdjustment;
use App\Ark\Operations\Labor\LaborAuthority;
use App\Ark\Operations\RepairOrders\ConcernBillingPosture;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLinePricing;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Support\Carbon;

beforeEach(function () {
    ShopSettings::current()->update([
        'labor_categories' => ShopSettings::DEFAULT_LABOR_CATEGORIES,
        'default_labor_rate_cents' => 16500,
    ]);
});

test('existing labor line keeps rate snapshot when policy changes', function () {
    $repairOrder = repairOrderForFinancialAuthority();
    $concern = concernForFinancialAuthority($repairOrder);

    $created = app(RepairOrderLinePricing::class)->attributesFor([
        'type' => RepairOrderLineType::Labor->value,
        'repair_order_concern_id' => $concern->id,
        'labor_category_key' => 'mechanical',
        'labor_entered_hours' => '1.00',
        'labor_adjustment' => LaborAdjustment::Normal->value,
    ], $repairOrder);

    expect($created['labor_rate_cents'])->toBe(16500);

    $line = RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Brake pads',
        'quantity' => $created['quantity'],
        'unit_price_cents' => $created['unit_price_cents'],
        'labor_category_key' => $created['labor_category_key'],
        'labor_category_name' => $created['labor_category_name'],
        'labor_entered_hours' => $created['labor_entered_hours'],
        'labor_adjustment' => $created['labor_adjustment'],
        'labor_adjustment_factor' => $created['labor_adjustment_factor'],
        'labor_billed_hours' => $created['labor_billed_hours'],
        'labor_rate_cents' => $created['labor_rate_cents'],
        'policy_resolved_labor_rate_cents' => $created['policy_resolved_labor_rate_cents'],
        'resolved_from_posture' => $created['resolved_from_posture'],
        'resolved_from_operation_class' => $created['resolved_from_operation_class'],
        'labor_policy_id' => $created['labor_policy_id'],
        'labor_policy_version' => $created['labor_policy_version'],
        'subtotal_cents' => $created['unit_price_cents'],
        'tax_cents' => 0,
        'shop_fee_cents' => 0,
        'standing_discount_cents' => 0,
        'total_cents' => $created['unit_price_cents'],
    ]);

    $class = OperationClass::query()->where('key', 'general_repair')->firstOrFail();
    app(UpsertLaborPolicyAction::class)->execute(
        ConcernBillingPosture::CustomerPay,
        $class,
        180.00,
        Carbon::parse('2026-07-20'),
        'policy bump',
    );

    $updated = app(RepairOrderLinePricing::class)->attributesFor([
        'type' => RepairOrderLineType::Labor->value,
        'repair_order_concern_id' => $concern->id,
        'labor_category_key' => 'mechanical',
        'labor_entered_hours' => '2.00',
        'labor_adjustment' => LaborAdjustment::Normal->value,
    ], $repairOrder, $line->fresh());

    expect($updated)
        ->labor_rate_cents->toBe(16500)
        ->unit_price_cents->toBe(16500)
        ->labor_entered_hours->toBe('2.00');
});

test('explicit reprice_labor refreshes rate from current policy', function () {
    $repairOrder = repairOrderForFinancialAuthority();
    $concern = concernForFinancialAuthority($repairOrder);
    $class = OperationClass::query()->where('key', 'general_repair')->firstOrFail();

    app(UpsertLaborPolicyAction::class)->execute(
        ConcernBillingPosture::CustomerPay,
        $class,
        180.00,
        Carbon::parse('2026-07-20'),
        'policy bump',
    );

    $line = RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Brake pads',
        'quantity' => '1.00',
        'unit_price_cents' => 16500,
        'labor_category_key' => 'mechanical',
        'labor_category_name' => 'Mechanical',
        'labor_entered_hours' => '1.00',
        'labor_adjustment' => LaborAdjustment::Normal->value,
        'labor_adjustment_factor' => '1.00',
        'labor_billed_hours' => '1.00',
        'labor_rate_cents' => 16500,
        'policy_resolved_labor_rate_cents' => 16500,
        'resolved_from_posture' => 'customer_pay',
        'resolved_from_operation_class' => 'general_repair',
        'subtotal_cents' => 16500,
        'tax_cents' => 0,
        'shop_fee_cents' => 0,
        'standing_discount_cents' => 0,
        'total_cents' => 16500,
    ]);

    $repriced = app(LaborAuthority::class)->resolveForLine([
        'labor_category_key' => 'mechanical',
        'labor_entered_hours' => '1.00',
        'labor_adjustment' => LaborAdjustment::Normal->value,
        'reprice_labor' => '1',
        'billing_posture' => ConcernBillingPosture::CustomerPay->value,
    ], ShopSettings::current(), $line);

    expect($repriced['labor_rate_cents'])->toBe(18000);
});

test('changing labor category re-resolves rate from the new category policy', function () {
    $repairOrder = repairOrderForFinancialAuthority();
    $concern = concernForFinancialAuthority($repairOrder);

    $line = RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Diagnostic',
        'quantity' => '1.00',
        'unit_price_cents' => 16500,
        'labor_category_key' => 'mechanical',
        'labor_category_name' => 'Mechanical',
        'labor_entered_hours' => '1.00',
        'labor_adjustment' => LaborAdjustment::Normal->value,
        'labor_adjustment_factor' => '1.00',
        'labor_billed_hours' => '1.00',
        'labor_rate_cents' => 16500,
        'policy_resolved_labor_rate_cents' => 16500,
        'resolved_from_posture' => 'customer_pay',
        'resolved_from_operation_class' => 'general_repair',
        'subtotal_cents' => 16500,
        'tax_cents' => 0,
        'shop_fee_cents' => 0,
        'standing_discount_cents' => 0,
        'total_cents' => 16500,
    ]);

    $updated = app(RepairOrderLinePricing::class)->attributesFor([
        'type' => RepairOrderLineType::Labor->value,
        'repair_order_concern_id' => $concern->id,
        'labor_category_key' => 'courtesy',
        'labor_entered_hours' => '1.00',
        'labor_adjustment' => LaborAdjustment::Normal->value,
    ], $repairOrder, $line->fresh());

    expect($updated)
        ->labor_category_key->toBe('courtesy')
        ->labor_rate_cents->toBe(0)
        ->unit_price_cents->toBe(0);
});

test('labor policy settings save does not rewrite existing line rates', function () {
    $repairOrder = repairOrderForFinancialAuthority();
    $concern = concernForFinancialAuthority($repairOrder);

    $line = RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Oil change',
        'quantity' => '1.00',
        'unit_price_cents' => 16500,
        'labor_rate_cents' => 16500,
        'labor_category_key' => 'mechanical',
        'subtotal_cents' => 16500,
        'tax_cents' => 0,
        'shop_fee_cents' => 0,
        'standing_discount_cents' => 0,
        'total_cents' => 16500,
    ]);

    $class = OperationClass::query()->where('key', 'maintenance')->firstOrFail();
    app(UpsertLaborPolicyAction::class)->execute(
        ConcernBillingPosture::CustomerPay,
        $class,
        199.00,
        Carbon::today(),
        'settings change',
    );

    expect($line->fresh()->labor_rate_cents)->toBe(16500)
        ->and($line->fresh()->unit_price_cents)->toBe(16500);
});
