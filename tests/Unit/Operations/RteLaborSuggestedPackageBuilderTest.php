<?php

use App\Ark\Operations\LaborGuides\Rte\RteLaborSuggestedPackageBuilder;
use App\Ark\Operations\LaborGuides\Rte\RteLaborVehicleEngineProfile;
use App\Ark\Operations\LaborGuides\Rte\RteShopLaborHoursProjection;

function shopProjection(): RteShopLaborHoursProjection
{
    return new RteShopLaborHoursProjection(
        enabledOverride: true,
        avgWeightTowardHiOverride: 0.85,
        hiCeilingMultiplierOverride: 1.08,
    );
}

function shopProjectLabor(array $row): array
{
    return shopProjection()->applyToRow($row);
}

function shopProjectIncluded(array $included): array
{
    return shopProjection()->applyToRow($included);
}

test('rte suggested package combines likely primary row and pooled related operations', function (): void {
    $profile = new RteLaborVehicleEngineProfile('6.4L HEMI');

    $recommended = shopProjectLabor([
        'lab_id' => '3461BTTT12199',
        'job_desc' => 'R & R RADIATOR',
        'lo_hr' => 0.8,
        'avg_hr' => 1.0,
        'hi_hr' => 1.2,
        'match_rank' => 3,
        'included_add_ons' => [
            shopProjectIncluded([
                'kind' => 'related_operation',
                'job_id_code' => '1421',
                'description' => 'DRAIN & FILL SYSTEM COOLING SYS',
                'lab_id' => '1421Bxxx22299',
                'lo_hr' => 0.4,
                'avg_hr' => 0.5,
                'hi_hr' => 0.7,
            ]),
        ],
        'optional_diagnostic_operations' => [],
    ]);

    $alternate = shopProjectLabor([
        'lab_id' => '3461BTxx12199',
        'job_desc' => 'R & R RADIATOR',
        'lo_hr' => 0.8,
        'avg_hr' => 1.0,
        'hi_hr' => 1.2,
        'match_rank' => 2,
        'included_add_ons' => [
            shopProjectIncluded([
                'kind' => 'related_operation',
                'job_id_code' => '1421',
                'description' => 'DRAIN & FILL SYSTEM COOLING SYS',
                'lab_id' => '1421BTxx22299',
                'lo_hr' => 0.5,
                'avg_hr' => 0.6,
                'hi_hr' => 0.8,
            ]),
        ],
        'optional_diagnostic_operations' => [
            shopProjectIncluded([
                'kind' => 'related_operation',
                'job_id_code' => '1431',
                'description' => 'COMBUSTION TEST COOLING SYSTEM',
                'lab_id' => '1431Bxxx22299',
                'lo_hr' => 0.3,
                'avg_hr' => 0.4,
                'hi_hr' => 0.6,
            ]),
        ],
    ]);

    $package = (new RteLaborSuggestedPackageBuilder)->build(
        $recommended,
        [$alternate],
        [$recommended, $alternate],
        $profile,
        'radiator',
    );

    expect($package)->not->toBeNull()
        ->and($package['line_count'])->toBe(2)
        ->and($package['lines'][1]['description'])->toBe('DRAIN & FILL SYSTEM COOLING SYS')
        ->and($package['lines'][1]['avg_hr'])->toBe(0.77)
        ->and($package['optional_diagnostic_operations'][0]['description'])->toBe('COMBUSTION TEST COOLING SYSTEM')
        ->and($package['total_avg_hr'])->toBe(1.94);
});

test('rte suggested package prefers higher related operation hours across variants', function (): void {
    $profile = new RteLaborVehicleEngineProfile('6.4L HEMI');

    $recommended = shopProjectLabor([
        'lab_id' => '3461BTTT12199',
        'job_desc' => 'R & R RADIATOR',
        'lo_hr' => 0.8,
        'avg_hr' => 1.0,
        'hi_hr' => 1.2,
        'match_rank' => 3,
        'included_add_ons' => [
            shopProjectIncluded([
                'kind' => 'related_operation',
                'job_id_code' => '1421',
                'description' => 'DRAIN & FILL SYSTEM COOLING SYS',
                'lab_id' => '1421BTTT22299',
                'lo_hr' => 0.4,
                'avg_hr' => 0.5,
                'hi_hr' => 0.7,
            ]),
        ],
    ]);

    $alternate = shopProjectLabor([
        'lab_id' => '3461BTxx12199',
        'job_desc' => 'R & R RADIATOR',
        'lo_hr' => 0.8,
        'avg_hr' => 1.0,
        'hi_hr' => 1.2,
        'match_rank' => 2,
        'included_add_ons' => [
            shopProjectIncluded([
                'kind' => 'related_operation',
                'job_id_code' => '1421',
                'description' => 'DRAIN & FILL SYSTEM COOLING SYS',
                'lab_id' => '1421BTxx22299',
                'lo_hr' => 0.5,
                'avg_hr' => 0.6,
                'hi_hr' => 0.8,
            ]),
        ],
    ]);

    $package = (new RteLaborSuggestedPackageBuilder)->build(
        $recommended,
        [$alternate],
        [$recommended, $alternate],
        $profile,
        'radiator',
    );

    expect($package['lines'][1]['avg_hr'])->toBe(0.77)
        ->and($package['lines'][1]['hi_hr'])->toBe(0.86);
});

test('rte suggested package prefers related operations from higher ranked variant rows when hours tie', function (): void {
    $profile = new RteLaborVehicleEngineProfile('6.4L HEMI');

    $recommended = shopProjectLabor([
        'lab_id' => '3461BTxx12199',
        'job_desc' => 'R & R RADIATOR',
        'lo_hr' => 0.8,
        'avg_hr' => 1.0,
        'hi_hr' => 1.2,
        'match_rank' => 2,
        'eng1' => 'B80xxx',
        'included_add_ons' => [],
    ]);

    $alternate = shopProjectLabor([
        'lab_id' => '3461BTTT12199',
        'job_desc' => 'R & R RADIATOR',
        'lo_hr' => 0.8,
        'avg_hr' => 1.0,
        'hi_hr' => 1.2,
        'match_rank' => 3,
        'eng1' => 'B803A0',
        'included_add_ons' => [
            shopProjectIncluded([
                'kind' => 'related_operation',
                'job_id_code' => '1421',
                'description' => 'DRAIN & FILL SYSTEM COOLING SYS',
                'lab_id' => '1421BTTT22299',
                'lo_hr' => 0.4,
                'avg_hr' => 0.6,
                'hi_hr' => 0.8,
            ]),
        ],
    ]);

    $package = (new RteLaborSuggestedPackageBuilder)->build(
        $recommended,
        [$alternate],
        [$recommended, $alternate],
        $profile,
        'radiator',
    );

    expect($package['lines'][1]['avg_hr'])->toBe(0.77)
        ->and($package['lines'][1]['lab_id'])->toBe('1421BTTT22299');
});
