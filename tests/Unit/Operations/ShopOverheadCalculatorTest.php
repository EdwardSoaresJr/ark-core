<?php

use App\Ark\Operations\Labor\ShopOverheadCalculator;

test('shop overhead calculator allocates monthly costs across billed hours', function () {
    expect(ShopOverheadCalculator::calculate(22000, 2, 8, 85, 22))->toBe(73.53)
        ->and(ShopOverheadCalculator::monthlyBillableHours(2, 8, 85, 22))->toBe(299.2);
});

test('shop overhead calculator returns null when inputs are incomplete', function () {
    expect(ShopOverheadCalculator::calculate(0, 2))->toBeNull()
        ->and(ShopOverheadCalculator::calculate(10000, 0))->toBeNull();
});
