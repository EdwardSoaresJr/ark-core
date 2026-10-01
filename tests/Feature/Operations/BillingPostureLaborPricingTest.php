<?php

use App\Ark\Operations\EstimatePricing\LaborPolicy;
use App\Ark\Operations\EstimatePricing\LaborRateType;
use App\Ark\Operations\EstimatePricing\OperationClass;
use App\Ark\Operations\RepairOrders\ConcernBillingPosture;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLinePricing;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    ShopSettings::current()->update([
        'labor_categories' => ShopSettings::DEFAULT_LABOR_CATEGORIES,
        'default_labor_rate_cents' => 16500,
    ]);
});

test('labor line pricing uses concern billing posture for policy resolution', function () {
    $class = OperationClass::query()->where('key', 'general_repair')->firstOrFail();

    LaborPolicy::query()->create([
        'billing_posture' => ConcernBillingPosture::Fleet->value,
        'operation_class_id' => $class->id,
        'rate_type' => LaborRateType::Hourly,
        'hourly_rate_cents' => 13500,
        'effective_from' => '2020-01-01',
        'effective_until' => null,
        'priority' => 10,
        'version' => 9,
    ]);

    $repairOrder = repairOrderForFinancialAuthority();
    $concern = concernForFinancialAuthority($repairOrder);
    $concern->update(['billing_posture' => ConcernBillingPosture::Fleet]);

    $pricing = app(RepairOrderLinePricing::class)->attributesFor([
        'type' => RepairOrderLineType::Labor->value,
        'repair_order_concern_id' => $concern->id,
        'labor_category_key' => 'mechanical',
        'labor_entered_hours' => '1.00',
        'labor_adjustment' => 'normal',
    ], $repairOrder);

    expect($pricing)
        ->labor_rate_cents->toBe(13500)
        ->unit_price_cents->toBe(13500)
        ->resolved_from_posture->toBe('fleet')
        ->resolved_from_operation_class->toBe('general_repair')
        ->labor_policy_version->toBe(9);
});

test('courtesy labor with concern posture resolves through policy not legacy category rate', function () {
    $repairOrder = repairOrderForFinancialAuthority();
    $concern = concernForFinancialAuthority($repairOrder);
    $concern->update(['billing_posture' => ConcernBillingPosture::CustomerPay]);

    $pricing = app(RepairOrderLinePricing::class)->attributesFor([
        'type' => RepairOrderLineType::Labor->value,
        'repair_order_concern_id' => $concern->id,
        'labor_category_key' => 'courtesy',
        'labor_entered_hours' => '1.00',
        'labor_adjustment' => 'normal',
    ], $repairOrder);

    expect($pricing)
        ->resolved_from_posture->toBe('customer_pay')
        ->labor_rate_cents->toBe(16500);
});
