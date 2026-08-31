<?php

use App\Ark\Operations\Labor\LaborExplanationProjection;
use App\Ark\Operations\LaborGuides\Rte\RteLaborHoursBasis;
use App\Ark\Operations\LaborGuides\Rte\RteShopLaborHoursProjection;
use Tests\TestCase;


test('labor explanation projection builds advisor summary for radiator package at shop avg with age padding', function (): void {
    $projection = new LaborExplanationProjection(
        new RteShopLaborHoursProjection(enabledOverride: true),
    );

    $result = $projection->project(
        lines: [
            [
                'label' => 'R & R RADIATOR',
                'lo_hr' => 1.0,
                'avg_hr' => 1.17,
                'hi_hr' => 1.30,
                'kind' => 'primary',
            ],
            [
                'label' => 'DRAIN & FILL SYSTEM COOLING SYS',
                'lo_hr' => 0.5,
                'avg_hr' => 0.67,
                'hi_hr' => 0.76,
                'kind' => 'related_operation',
            ],
            [
                'label' => 'COMBUSTION TEST COOLING SYSTEM',
                'lo_hr' => 0.4,
                'avg_hr' => 0.57,
                'hi_hr' => 0.65,
                'kind' => 'related_operation',
            ],
        ],
        modelYear: 2010,
        basis: RteLaborHoursBasis::Avg,
        applyAgePadding: true,
    );

    expect($result['advisor_summary']['total_hours'])->toBe(2.78)
        ->and($result['advisor_summary']['tier_label'])->toBe('Shop Avg')
        ->and($result['advisor_summary']['age_label'])->toBe('Age +15%')
        ->and($result['advisor_summary']['includes'])->toBe([
            'Coolant drain & refill',
            'Cooling system verification',
        ])
        ->and($result['advisor_detail']['lines'])->toBe([
            ['label' => 'Radiator', 'hours' => 1.35],
            ['label' => 'Drain & Fill', 'hours' => 0.77],
            ['label' => 'Combustion Test', 'hours' => 0.66],
        ])
        ->and($result['advisor_detail']['vehicle_age_years'])->toBeGreaterThanOrEqual(15)
        ->and($result['advisor_detail']['tier_label'])->toBe('Shop Avg')
        ->and($result['engineering_detail']['basis'])->toBe('avg');
});

test('labor explanation projection omits age label for shop lo tier', function (): void {
    $projection = new LaborExplanationProjection(
        new RteShopLaborHoursProjection(enabledOverride: true),
    );

    $result = $projection->project(
        lines: [
            [
                'label' => 'R & R RADIATOR',
                'lo_hr' => 1.0,
                'avg_hr' => 1.17,
                'hi_hr' => 1.30,
                'kind' => 'primary',
            ],
        ],
        modelYear: 2010,
        basis: RteLaborHoursBasis::Lo,
        applyAgePadding: true,
    );

    expect($result['advisor_summary']['age_label'])->toBeNull()
        ->and($result['advisor_summary']['total_hours'])->toBe(1.0);
});

test('labor explanation variant matrix strips engineering detail from advisor payloads', function (): void {
    $projection = new LaborExplanationProjection(
        new RteShopLaborHoursProjection(enabledOverride: true),
    );

    $matrix = $projection->variantMatrix(
        lines: [
            [
                'label' => 'R & R RADIATOR',
                'lo_hr' => 1.0,
                'avg_hr' => 1.17,
                'hi_hr' => 1.30,
                'kind' => 'primary',
            ],
        ],
        modelYear: 2024,
    );

    expect($matrix)->toHaveKeys(['lo', 'avg', 'hi'])
        ->and($matrix['avg']['padding_on'])->toHaveKeys(['advisor_summary', 'advisor_detail'])
        ->and($matrix['avg']['padding_on'])->not->toHaveKey('engineering_detail');
});
