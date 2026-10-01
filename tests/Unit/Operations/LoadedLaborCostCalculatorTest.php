<?php

use App\Ark\Operations\Labor\LoadedLaborCostCalculator;
use App\Ark\Operations\Labor\TechnicianLaborPayBasis;

test('loaded labor cost calculator applies burden utilization and overhead for hourly pay', function () {
    expect(LoadedLaborCostCalculator::calculate(30, 28, 5, 85))->toBe(50.18)
        ->and(LoadedLaborCostCalculator::calculate(25, 0, 0, 100))->toBe(25.0)
        ->and(LoadedLaborCostCalculator::calculate(40, 20, 0, 80))->toBe(60.0);
});

test('flag pay uses max of flag rate and floor-equivalent production cost', function () {
    // Healthy util: flag $30 dominates floor $15.16 ÷ 0.85 ≈ $17.84
    expect(LoadedLaborCostCalculator::calculate(
        30,
        28,
        5,
        85,
        TechnicianLaborPayBasis::Flag,
        15.16,
    ))->toBe(43.4);

    // Poor util: floor $15.16 ÷ 0.50 = $30.32 dominates $30 flag
    expect(LoadedLaborCostCalculator::calculate(
        30,
        28,
        5,
        50,
        TechnicianLaborPayBasis::Flag,
        15.16,
    ))->toBe(43.81);

    // No floor: same as flag-only path
    expect(LoadedLaborCostCalculator::calculate(30, 28, 5, 85, TechnicianLaborPayBasis::Flag))->toBe(43.4)
        ->and(LoadedLaborCostCalculator::calculate(25, 0, 0, 85, TechnicianLaborPayBasis::Flag))->toBe(25.0);
});

test('effective wage cost per billed hour is a projection not a pay rate', function () {
    expect(LoadedLaborCostCalculator::effectiveWageCostPerBilledHour(30, 15.16, 85))->toBe(30.0)
        ->and(round(LoadedLaborCostCalculator::effectiveWageCostPerBilledHour(30, 15.16, 50), 2))->toBe(30.32);
});

test('loaded labor cost calculator clamps invalid utilization input', function () {
    expect(LoadedLaborCostCalculator::calculate(30, 0, 0, 0))->toBe(3000.0)
        ->and(LoadedLaborCostCalculator::calculate(30, 0, 0, 150))->toBe(30.0);
});
