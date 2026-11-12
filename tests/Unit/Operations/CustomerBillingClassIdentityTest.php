<?php

use App\Ark\Operations\EstimatePricing\CustomerBillingClassIdentity;
use App\Ark\Operations\RepairOrders\ConcernBillingPosture;
use App\Ark\Operations\Settings\ShopSettings;

test('built-in customer classes receive stable keys and explicit labor links', function () {
    $rows = CustomerBillingClassIdentity::ensureKeysOnRows(ShopSettings::DEFAULT_CUSTOMER_TYPES);

    $byKey = collect($rows)->keyBy('key');

    expect($byKey['retail']['labor_column_key'])->toBe('customer_pay')
        ->and($byKey['fleet']['labor_column_key'])->toBe('fleet')
        ->and($byKey['warranty']['labor_column_key'])->toBe('warranty_other')
        ->and($byKey['repairpal']['labor_column_key'])->toBe('repairpal')
        ->and($byKey['internal']['labor_column_key'])->toBe('internal')
        ->and($byKey['wholesale']['labor_column_key'])->toBe('wholesale')
        ->and($byKey['comeback']['labor_column_key'])->toBe('comeback')
        ->and($byKey['business'])->not->toHaveKey('labor_column_key')
        ->and($byKey['military'])->not->toHaveKey('labor_column_key')
        ->and(CustomerBillingClassIdentity::isCustomerOnlyClassKey('military'))->toBeTrue()
        ->and(CustomerBillingClassIdentity::isCustomerOnlyClassKey('business'))->toBeTrue();
});

test('renaming a display label does not change an existing stable key', function () {
    $key = CustomerBillingClassIdentity::keyForRow([
        'key' => 'retail',
        'name' => 'Walk-in Retail',
    ]);

    expect($key)->toBe('retail');
});

test('custom classes mint collision-safe keys without inventing labor links', function () {
    $rows = CustomerBillingClassIdentity::ensureKeysOnRows([
        ['name' => 'VIP Fleet'],
        ['name' => 'VIP Fleet'],
        ['name' => 'Military Discount Clone'],
    ]);

    expect($rows[0]['key'])->toBe('vip_fleet')
        ->and($rows[1]['key'])->toBe('vip_fleet_2')
        ->and($rows[0])->not->toHaveKey('labor_column_key')
        ->and($rows[1])->not->toHaveKey('labor_column_key')
        ->and($rows[2]['key'])->toBe('military_discount_clone')
        ->and($rows[2])->not->toHaveKey('labor_column_key');
});

test('similar names never cross-link labor columns or customer classes', function () {
    expect(CustomerBillingClassIdentity::classKeyForLaborColumn('custom_vip_fleet'))->toBeNull()
        ->and(CustomerBillingClassIdentity::classKeyForLaborColumn('fleet'))->toBe('fleet')
        ->and(CustomerBillingClassIdentity::classKeyForLaborColumn(ConcernBillingPosture::CustomerPay->value))->toBe('retail')
        ->and(CustomerBillingClassIdentity::classKeyForLaborColumn('warranty_other'))->toBe('warranty')
        ->and(CustomerBillingClassIdentity::laborColumnForClassKey('military'))->toBeNull()
        ->and(CustomerBillingClassIdentity::laborColumnForClassKey('business'))->toBeNull();
});

test('legacy rows without keys remain readable after ensureKeysOnRows', function () {
    $legacy = [
        ['name' => 'Retail', 'shop_fees_enabled' => true, 'discount_type' => 'none'],
        ['name' => 'Shop Special', 'shop_fees_enabled' => true, 'discount_type' => 'labor', 'discount_amount' => '5.00'],
    ];

    $rows = CustomerBillingClassIdentity::ensureKeysOnRows($legacy);

    expect($rows[0]['key'])->toBe('retail')
        ->and($rows[0]['labor_column_key'])->toBe('customer_pay')
        ->and($rows[0]['name'])->toBe('Retail')
        ->and($rows[1]['key'])->toBe('shop_special')
        ->and($rows[1])->not->toHaveKey('labor_column_key')
        ->and($rows[1]['discount_amount'])->toBe('5.00');
});
