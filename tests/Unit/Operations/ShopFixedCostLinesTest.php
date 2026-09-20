<?php

use App\Ark\Operations\Labor\ShopFixedCostLines;
use App\Ark\Operations\Labor\ShopFixedCostPeriod;

test('shop fixed cost period converts weekly amounts to monthly', function () {
    expect(ShopFixedCostPeriod::toMonthly(1400, ShopFixedCostPeriod::WEEKLY))->toBe(6066.67)
        ->and(ShopFixedCostPeriod::toMonthly(3000, ShopFixedCostPeriod::MONTHLY))->toBe(3000.0)
        ->and(ShopFixedCostPeriod::toMonthly(12000, ShopFixedCostPeriod::ANNUAL))->toBe(1000.0);
});

test('shop fixed cost lines normalize and total monthly equivalents', function () {
    $lines = ShopFixedCostLines::normalize([
        ['label' => 'Rent', 'amount' => '3300', 'period' => 'monthly'],
        ['label' => 'Payroll', 'amount' => '1400', 'period' => 'weekly'],
        ['label' => '', 'amount' => '100', 'period' => 'monthly'],
    ]);

    expect($lines)->toHaveCount(2)
        ->and(ShopFixedCostLines::monthlyTotal($lines))->toBe(9366.67);
});

test('legacy shop overhead costs migrate into monthly lines', function () {
    $lines = ShopFixedCostLines::fromLegacyCosts([
        'rent' => '3300',
        'insurance' => '200',
        'office_payroll' => '6067',
    ]);

    expect($lines)->toHaveCount(3)
        ->and($lines[0]['label'])->toBe('Rent / mortgage')
        ->and(ShopFixedCostLines::monthlyTotal($lines))->toBe(9567.0);
});
