<?php

use App\Ark\Operations\Settings\ShopSettings;

test('billing class profiles are recognized', function () {
    $settings = new ShopSettings([
        'customer_types' => ShopSettings::DEFAULT_CUSTOMER_TYPES,
        'shop_fee_enabled' => true,
        'shop_fee_rate' => '3.500',
    ]);

    expect($settings->customerTagBillingProfile('Fleet'))->toBe('fleet')
        ->and($settings->customerTagBillingProfile('Warranty'))->toBe('warranty')
        ->and($settings->customerTagBillingProfile('RepairPal'))->toBe('repairpal')
        ->and($settings->customerTagBillingProfile('Retail'))->toBeNull()
        ->and($settings->shopFeePolicyForCustomerType('Fleet')['enabled'])->toBeTrue()
        ->and($settings->shopFeePolicyForBillingDefault()['enabled'])->toBeTrue();
});

test('legacy fee_override none maps to disabled shop fees', function () {
    $settings = new ShopSettings;

    $row = $settings->normalizeCustomerTypeRow([
        'name' => 'Warranty',
        'fee_override' => 'none',
        'discount_type' => 'none',
        'discount_amount' => null,
        'default_parts_matrix_key' => null,
    ]);

    expect($row['shop_fees_enabled'])->toBeFalse()
        ->and($row['shop_fee_rate_override'])->toBeNull();
});
