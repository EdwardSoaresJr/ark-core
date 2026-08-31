<?php

use App\Ark\Operations\LaborGuides\Rte\RteShopLaborHoursProjection;
use Tests\TestCase;


test('shop labor projection maps book average to lo and weighted high to avg', function (): void {
    $projection = new RteShopLaborHoursProjection(
        enabledOverride: true,
        avgWeightTowardHiOverride: 0.85,
        hiCeilingMultiplierOverride: 1.08,
    );

    expect($projection->project(0.8, 1.0, 1.2))->toBe([
        'lo_hr' => 1.0,
        'avg_hr' => 1.17,
        'hi_hr' => 1.3,
    ]);
});

test('shop labor age padding applies to avg and hi only when enabled separately', function (): void {
    $projection = new RteShopLaborHoursProjection(
        enabledOverride: true,
        avgWeightTowardHiOverride: 0.85,
        hiCeilingMultiplierOverride: 1.08,
    );

    $base = $projection->project(0.8, 1.0, 1.2);
    $padded = $projection->applyAgePaddingToHours($base, $projection->vehicleAgeMultiplier(2012));

    expect($padded)->toBe([
        'lo_hr' => 1.0,
        'avg_hr' => 1.35,
        'hi_hr' => 1.49,
    ]);
});

test('shop labor age padding leaves lo unchanged so advisors can still discount', function (): void {
    $projection = new RteShopLaborHoursProjection(enabledOverride: true);

    $base = $projection->project(0.8, 1.0, 1.2);
    $padded = $projection->applyAgePaddingToHours($base, $projection->vehicleAgeMultiplier(2005));

    expect($padded['lo_hr'])->toBe(1.0);
});

test('shop labor projection leaves equal bundled hours unchanged without age padding', function (): void {
    $projection = new RteShopLaborHoursProjection(
        enabledOverride: true,
        avgWeightTowardHiOverride: 0.85,
        hiCeilingMultiplierOverride: 1.08,
    );

    expect($projection->project(0.3, 0.3, 0.3))->toBe([
        'lo_hr' => 0.3,
        'avg_hr' => 0.3,
        'hi_hr' => 0.32,
    ]);
});

test('shop labor vehicle age multiplier steps up for older model years', function (): void {
    $projection = new RteShopLaborHoursProjection(enabledOverride: true);

    expect($projection->vehicleAgeMultiplier(2024))->toBe(1.0)
        ->and($projection->vehicleAgeMultiplier(2014))->toBe(1.08)
        ->and($projection->vehicleAgeMultiplier(2012))->toBe(1.15)
        ->and($projection->vehicleAgeMultiplier(2005))->toBe(1.22);
});
