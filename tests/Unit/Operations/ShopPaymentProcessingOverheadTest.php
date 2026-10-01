<?php

use App\Ark\Operations\Labor\ShopPaymentProcessingOverhead;

test('payment processing overhead calculates processor fee and holdback after fees', function () {
    expect(ShopPaymentProcessingOverhead::monthlyProcessingCost(100000, 2.9))->toBe(2900.0)
        ->and(ShopPaymentProcessingOverhead::monthlyFinancingCost(100000, 2.9, 10))->toBe(9710.0)
        ->and(ShopPaymentProcessingOverhead::monthlyPaymentOverheadTotal(100000, 2.9, 10))->toBe(12610.0);
});

test('payment processing overhead accepts fixed monthly financing payment', function () {
    expect(ShopPaymentProcessingOverhead::monthlyFinancingCost(100000, 2.9, 10, 1500.0))->toBe(1500.0)
        ->and(ShopPaymentProcessingOverhead::monthlyPaymentOverheadTotal(100000, 2.9, 10, 1500.0))->toBe(4400.0);
});
