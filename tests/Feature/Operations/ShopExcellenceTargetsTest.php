<?php

use App\Ark\Operations\ShopExcellence\ShopExcellenceTargets;
use Database\Seeders\ArkAuthorizationSeeder;

test('shop excellence targets load defaults from shop settings', function () {
    $this->seed(ArkAuthorizationSeeder::class);

    $targets = ShopExcellenceTargets::current();

    expect($targets['parts_margin_target_percent'])->toBe(55)
        ->and($targets['aro_target_cents'])->toBe(75000)
        ->and($targets['labor_sales_target_percent'])->toBe(55)
        ->and($targets['parts_sales_target_percent'])->toBe(45);
});

test('shop excellence target tone helpers classify against floors', function () {
    expect(ShopExcellenceTargets::toneForMinimum(15000, 12000))->toBe('good')
        ->and(ShopExcellenceTargets::toneForMinimum(10000, 12000))->toBe('warn')
        ->and(ShopExcellenceTargets::toneForMinimumPercent(58, 55))->toBe('good')
        ->and(ShopExcellenceTargets::toneForMinimumPercent(44, 55))->toBe('warn')
        ->and(ShopExcellenceTargets::toneForMixPercent(55, 55))->toBe('good')
        ->and(ShopExcellenceTargets::toneForMixPercent(40, 55))->toBe('warn');
});

test('target review stale when never reviewed or older than a quarter', function () {
    expect(ShopExcellenceTargets::lastTargetReview())->toBeNull()
        ->and(ShopExcellenceTargets::targetReviewStale())->toBeTrue();
});
