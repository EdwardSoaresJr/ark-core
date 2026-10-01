<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Encounters\EncounterSource;
use App\Ark\Operations\RepairOrders\ConcernBillingPosture;
use App\Ark\Operations\Settings\ShopSettings;

test('repairpal billing class defaults to repairpal scope posture', function () {
    $customer = new Customer([
        'customer_type' => 'RepairPal',
        'referral_source' => EncounterSource::Website->value,
    ]);

    expect(ConcernBillingPosture::defaultForCustomer($customer))
        ->toBe(ConcernBillingPosture::RepairPal);
});

test('repairpal referral does not override retail billing class', function () {
    $customer = new Customer([
        'customer_type' => 'Retail',
        'referral_source' => EncounterSource::RepairPal->value,
    ]);

    expect(ConcernBillingPosture::defaultForCustomer($customer))
        ->toBe(ConcernBillingPosture::Default);
});

test('warranty billing class defaults to warranty scope posture', function () {
    $customer = new Customer([
        'customer_type' => 'Warranty',
        'referral_source' => EncounterSource::RepairPal->value,
    ]);

    expect(ConcernBillingPosture::defaultForCustomer($customer))
        ->toBe(ConcernBillingPosture::WarrantyOther);
});

test('comeback billing class defaults to comeback scope posture', function () {
    $customer = new Customer(['customer_type' => 'Comeback']);

    expect(ConcernBillingPosture::defaultForCustomer($customer))
        ->toBe(ConcernBillingPosture::Comeback);
});

test('repairpal billing posture uses repairpal labor category rate', function () {
    $settings = new ShopSettings([
        'labor_categories' => ShopSettings::DEFAULT_LABOR_CATEGORIES,
        'default_labor_rate_cents' => 16500,
    ]);

    expect($settings->laborDefaultsForBillingPosture(ConcernBillingPosture::RepairPal))
        ->toMatchArray([
            'category_key' => ShopSettings::WARRANTY_REPAIRPAL_LABOR_CATEGORY_KEY,
            'rate_cents' => 15000,
            'rate' => '150.00',
        ]);
});

test('comeback billing posture uses zero comeback labor category', function () {
    $settings = new ShopSettings([
        'labor_categories' => ShopSettings::DEFAULT_LABOR_CATEGORIES,
        'default_labor_rate_cents' => 16500,
    ]);

    expect($settings->laborDefaultsForBillingPosture(ConcernBillingPosture::Comeback))
        ->toMatchArray([
            'category_key' => ShopSettings::COMEBACK_LABOR_CATEGORY_KEY,
            'rate_cents' => 0,
            'rate' => '0.00',
        ]);
});

test('billing posture option labels include labor rate matrix and shop fees inline', function () {
    $settings = new ShopSettings([
        'labor_categories' => ShopSettings::DEFAULT_LABOR_CATEGORIES,
        'default_labor_rate_cents' => 16500,
        'parts_matrices' => ShopSettings::DEFAULT_PARTS_MATRICES,
        'shop_fee_enabled' => true,
        'shop_fee_rate' => 5,
    ]);

    $default = $settings->billingPostureOptionPresentation(ConcernBillingPosture::Default);

    expect($default['label'])->toBe('Shop default · $165.00/hr · AFT Parts · 5% fees')
        ->and($default['title'])->toContain('$165.00/hr labor')
        ->and($default['title'])->toContain('AFT Parts parts');

    $repairPal = $settings->billingPostureOptionPresentation(ConcernBillingPosture::RepairPal);

    expect($repairPal['label'])->toBe('RepairPal · $150.00/hr · Warranty (No Markup) · No fees')
        ->and($repairPal['title'])->toContain('No shop fees');
});

test('legacy warranty repairpal posture resolves to repairpal', function () {
    expect(ConcernBillingPosture::fromStored('warranty_repairpal'))
        ->toBe(ConcernBillingPosture::RepairPal);
});

test('comeback and warranty billing postures prefer manual part pricing', function () {
    expect(ConcernBillingPosture::Comeback->prefersManualPartPricing())->toBeTrue()
        ->and(ConcernBillingPosture::Internal->prefersManualPartPricing())->toBeTrue()
        ->and(ConcernBillingPosture::WarrantyOther->prefersManualPartPricing())->toBeTrue()
        ->and(ConcernBillingPosture::CustomerPay->prefersManualPartPricing())->toBeFalse()
        ->and(ConcernBillingPosture::Default->prefersManualPartPricing())->toBeFalse();
});

test('primary billing classes include comeback and repairpal', function () {
    $settings = new ShopSettings([
        'customer_types' => ShopSettings::DEFAULT_CUSTOMER_TYPES,
    ]);

    expect(collect($settings->primaryBillingClassRows())->pluck('name')->all())
        ->toContain('RepairPal', 'Comeback', 'Internal', 'Wholesale');
});
