<?php

use App\Ark\Operations\Labor\LaborMatchAttributionProjection;
use App\Ark\Operations\LaborGuides\Rte\RteLaborHoursBasis;
use App\Ark\Operations\LaborGuides\Rte\RteShopLaborHoursProjection;
use Tests\TestCase;


test('labor match attribution breaks guide hours through shop and age layers', function (): void {
    $projection = new LaborMatchAttributionProjection(
        new RteShopLaborHoursProjection(enabledOverride: true),
    );

    $result = $projection->build(
        matchContext: [
            'selected_application' => 'R 2500-3500 4X4 PICK-UP (2003-2014)',
            'vehicle_label' => '2010 Ram 2500',
            'vehicle_engine_label' => '5.7L HEMI',
        ],
        lines: [
            [
                'label' => 'R & R RADIATOR',
                'kind' => 'primary',
                'lab_id' => '3461BTTT12199',
                'book_avg_hr' => 1.0,
                'avg_hr' => 1.17,
            ],
            [
                'label' => 'COMBUSTION TEST COOLING SYSTEM',
                'kind' => 'related_operation',
                'lab_id' => '1431Bxxx22299',
                'book_avg_hr' => 0.4,
                'avg_hr' => 0.57,
                'source_lab_id' => '3461BTTT12199',
            ],
            [
                'label' => 'DRAIN & FILL SYSTEM COOLING SYS',
                'kind' => 'related_operation',
                'lab_id' => '1421Bxxx22299',
                'book_avg_hr' => 0.5,
                'avg_hr' => 0.67,
                'source_lab_id' => '3461BTTT12199',
            ],
        ],
        modelYear: 2010,
        basis: RteLaborHoursBasis::Avg,
        applyAgePadding: true,
        primaryLabId: '3461BTTT12199',
    );

    expect($result)->not->toBeNull()
        ->and($result['selected_application'])->toBe('R 2500-3500 4X4 PICK-UP (2003-2014)')
        ->and($result['vehicle'])->toBe('2010 Ram 2500 · 5.7L HEMI')
        ->and($result['primary']['guide_row'])->toBe('R & R RADIATOR')
        ->and($result['primary']['guide_hours'])->toBe(1.0)
        ->and($result['primary']['shop_adjustment'])->toBe(0.17)
        ->and($result['primary']['age_adjustment'])->toBe(0.18)
        ->and($result['primary']['final_hours'])->toBe(1.35)
        ->and($result['adjustments'])->toBe(['Shop Avg +0.17', 'Age +0.18'])
        ->and($result['related_operations'])->toHaveCount(2)
        ->and($result['related_operations'][0]['display_label'])->toBe('Combustion Test')
        ->and($result['related_operations'][0]['final_hours'])->toBe(0.66)
        ->and($result['related_operations'][1]['display_label'])->toBe('Drain & Fill')
        ->and($result['related_operations'][1]['final_hours'])->toBe(0.77)
        ->and($result['final_total'])->toBe(2.78);
});
