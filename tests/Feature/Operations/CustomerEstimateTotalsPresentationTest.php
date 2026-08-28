<?php

use App\Ark\Operations\Financial\CustomerEstimateTotalsPresentation;
use App\Ark\Operations\RepairOrders\EstimateTotals;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Support\Collection;

test('customer estimate totals presentation uses gross labor and subtracts standing discount before total', function (): void {
    $totals = new EstimateTotals(
        lines: Collection::make(),
        concernSubtotals: [],
        laborCents: 29700,
        partsCents: 17892,
        feesCents: 1666,
        taxCents: 1468,
        taxableSellCents: 17892,
        allocatedShopFeesCents: 1666,
        standingDiscountCents: 3300,
        grossLaborCents: 33000,
        grossPartsCents: 17892,
    );

    $breakdown = CustomerEstimateTotalsPresentation::fromEstimateTotals(
        $totals,
        ShopSettings::current(),
        'Military Discount',
    );

    expect($breakdown['labor_cents'])->toBe(33000)
        ->and($breakdown['standing_discount_cents'])->toBe(3300)
        ->and($breakdown['subtotal_before_tax_cents'])->toBe(49258)
        ->and($breakdown['total_cents'])->toBe(50726)
        ->and($breakdown['subtotal_before_tax_cents'] + $breakdown['tax_cents'])->toBe($breakdown['total_cents']);
});

test('customer tax label clarifies parts-only tax and excludes fees', function (): void {
    $settings = new ShopSettings([
        'tax_enabled' => true,
        'tax_label' => 'C/S Tax',
        'taxable_labor' => false,
        'taxable_parts' => true,
        'taxable_shop_fees' => false,
    ]);

    expect(CustomerEstimateTotalsPresentation::customerTaxLabel($settings))
        ->toBe('C/S Tax (parts)');
});

test('legacy snapshot totals upgrade net labor to gross when standing discount is present', function (): void {
    $breakdown = CustomerEstimateTotalsPresentation::fromSnapshotTotals([
        'labor_cents' => 29700,
        'parts_cents' => 17892,
        'fees_cents' => 1666,
        'standing_discount_cents' => 3300,
        'tax_cents' => 1468,
        'total_cents' => 50726,
        'standing_discount_label' => 'Military Discount',
        'customer_tax_label' => 'C/S Tax (parts)',
    ], 'Military');

    expect($breakdown['labor_cents'])->toBe(33000)
        ->and($breakdown['subtotal_before_tax_cents'])->toBe(49258)
        ->and($breakdown['total_cents'])->toBe(50726);
});
