<?php

use App\Ark\Operations\Labor\LaborAdjustment;
use App\Ark\Operations\Labor\LaborAuthority;
use App\Ark\Operations\OperationAuthority\Operation;
use App\Ark\Operations\RepairOrders\ConcernBillingPosture;
use App\Ark\Operations\Settings\ShopSettings;

test('operation owns operation class via operationClassKey', function () {
    $operation = Operation::forLine(null, 'mechanical');

    expect($operation->code)->toBe('mechanical')
        ->and($operation->operationClassKey())->toBe('general_repair');
});

test('labor authority consumes operation class key only', function () {
    expect(class_exists(\App\Ark\Operations\OperationAuthority\OperationResolver::class))->toBeFalse();
    expect(class_exists(\App\Ark\Operations\EstimatePricing\LaborCategoryOperationClassMap::class))->toBeFalse();

    $resolved = app(LaborAuthority::class)->resolveForLine([
        'labor_category_key' => 'diagnostic',
        'labor_entered_hours' => '1.00',
        'labor_adjustment' => LaborAdjustment::Normal->value,
    ], ShopSettings::current());

    expect($resolved)
        ->resolved_from_operation_class->toBe('diagnostics')
        ->operation_id->not->toBeNull();

    expect(Operation::query()->findOrFail($resolved['operation_id'])->operationClassKey())->toBe('diagnostics');
});

test('repairpal labor category still projects repairpal posture in labor authority', function () {
    $resolved = app(LaborAuthority::class)->resolveForLine([
        'labor_category_key' => ShopSettings::WARRANTY_REPAIRPAL_LABOR_CATEGORY_KEY,
        'labor_entered_hours' => '1.00',
    ], ShopSettings::current());

    expect($resolved)
        ->resolved_from_posture->toBe('repairpal')
        ->labor_rate_cents->toBe(15000);
});

test('explicit operation_id selects that operations class', function () {
    $maintenance = Operation::query()->where('code', 'maintenance')->firstOrFail();

    $resolved = app(LaborAuthority::class)->resolveForLine([
        'labor_category_key' => 'mechanical',
        'operation_id' => $maintenance->id,
        'labor_entered_hours' => '1.00',
        'labor_adjustment' => LaborAdjustment::Normal->value,
        'billing_posture' => ConcernBillingPosture::CustomerPay->value,
    ], ShopSettings::current());

    expect($resolved)
        ->operation_id->toBe($maintenance->id)
        ->resolved_from_operation_class->toBe('maintenance');
});
