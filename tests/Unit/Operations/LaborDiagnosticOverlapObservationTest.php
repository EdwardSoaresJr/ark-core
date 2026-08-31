<?php

use App\Ark\Operations\Labor\LaborDiagnosticLaborMatcher;
use App\Ark\Operations\Labor\LaborDiagnosticOverlapObservation;
use Tests\TestCase;


test('diagnostic labor matcher recognizes combustion and diagnosis descriptions', function (): void {
    $matcher = new LaborDiagnosticLaborMatcher;

    expect($matcher->isDiagnosticTestingDescription('COMBUSTION TEST COOLING SYSTEM'))->toBeTrue()
        ->and($matcher->isDiagnosticTestingDescription('Cooling system overheating diagnosis'))->toBeTrue()
        ->and($matcher->isDiagnosticTestingDescription('R & R RADIATOR'))->toBeFalse()
        ->and($matcher->isDiagnosticTestingDescription('DRAIN & FILL SYSTEM COOLING SYS'))->toBeFalse();
});

test('diagnostic overlap observation warns when package includes test and ro already has diagnostic labor', function (): void {
    $observation = new LaborDiagnosticOverlapObservation;

    $result = $observation->detect(
        packageLines: [
            [
                'label' => 'R & R RADIATOR',
                'kind' => 'primary',
            ],
            [
                'label' => 'COMBUSTION TEST COOLING SYSTEM',
                'kind' => 'related_operation',
            ],
        ],
        existingLaborLines: [
            [
                'id' => 10,
                'description' => 'Cooling system overheating diagnosis',
                'repair_order_concern_id' => 5,
                'concern_summary' => 'Overheating concern',
            ],
        ],
        targetConcernId: 5,
    );

    expect($result)->not->toBeNull()
        ->and($result['advisor_summary']['overlap_warning'])->toBe(LaborDiagnosticOverlapObservation::ADVISOR_WARNING)
        ->and($result['advisor_detail']['diagnostic_overlap']['package_line']['label'])->toBe('Combustion Test')
        ->and($result['advisor_detail']['diagnostic_overlap']['existing_line']['description'])
        ->toBe('Cooling system overheating diagnosis')
        ->and($result['advisor_detail']['diagnostic_overlap']['existing_line']['same_concern'])->toBeTrue()
        ->and($result['engineering_detail']['existing_line']['id'])->toBe(10);
});

test('diagnostic overlap observation stays silent without existing diagnostic labor', function (): void {
    $observation = new LaborDiagnosticOverlapObservation;

    expect($observation->detect(
        packageLines: [
            ['label' => 'COMBUSTION TEST COOLING SYSTEM', 'kind' => 'related_operation'],
        ],
        existingLaborLines: [
            [
                'id' => 11,
                'description' => 'R & R RADIATOR',
                'repair_order_concern_id' => 5,
                'concern_summary' => 'Radiator',
            ],
        ],
        targetConcernId: 5,
    ))->toBeNull();
});
