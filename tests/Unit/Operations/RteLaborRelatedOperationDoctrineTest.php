<?php

use App\Ark\Operations\LaborGuides\Rte\RteLaborRelatedOperationDoctrine;

test('rte related operation doctrine separates diagnostic operations from repair operations', function (): void {
    $doctrine = new RteLaborRelatedOperationDoctrine;

    $partitioned = $doctrine->partition([
        [
            'description' => 'DRAIN & FILL SYSTEM COOLING SYS',
            'lab_id' => '1421Bxxx22299',
        ],
        [
            'description' => 'COMBUSTION TEST COOLING SYSTEM',
            'lab_id' => '1431Bxxx22299',
        ],
    ]);

    expect($partitioned['repair_related'])->toHaveCount(1)
        ->and($partitioned['repair_related'][0]['description'])->toBe('DRAIN & FILL SYSTEM COOLING SYS')
        ->and($partitioned['optional_diagnostic'])->toHaveCount(1)
        ->and($partitioned['optional_diagnostic'][0]['description'])->toBe('COMBUSTION TEST COOLING SYSTEM');
});

test('rte related operation doctrine treats combustion test descriptions as diagnostic', function (): void {
    $doctrine = new RteLaborRelatedOperationDoctrine;

    expect($doctrine->isDiagnosticRelatedOperation([
        'description' => 'COMBUSTION TEST COOLING SYSTEM',
    ]))->toBeTrue()
        ->and($doctrine->isDiagnosticRelatedOperation([
            'description' => 'DRAIN & FILL SYSTEM COOLING SYS',
        ]))->toBeFalse();
});
