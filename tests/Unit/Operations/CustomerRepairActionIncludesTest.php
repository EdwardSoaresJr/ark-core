<?php

use App\Ark\Operations\Documents\CustomerRepairActionIncludes;

test('expands R&R repair action titles into customer includes bullets', function () {
    expect(CustomerRepairActionIncludes::expandTitle('Brake Pads & Rotors, R&R'))
        ->toBe([
            'Replace brake pads',
            'Replace rotors',
        ]);
});

test('bullets for concern skip empty groups and duplicate concern titles', function () {
    $bullets = CustomerRepairActionIncludes::bulletsForConcern('Front Brake Service', [
        [
            'title' => 'Brake Pads & Rotors, R&R',
            'lines' => [['description' => 'Labor']],
        ],
        [
            'title' => 'Front Brake Service',
            'lines' => [['description' => 'Labor']],
        ],
        [
            'title' => 'Inspect Brakes',
            'lines' => [],
        ],
    ]);

    expect($bullets)->toBe([
        'Replace brake pads',
        'Replace rotors',
    ]);
});

test('group heading softens a single repair action title for line lists', function () {
    expect(CustomerRepairActionIncludes::groupHeading('Brake Pads & Rotors, R&R'))
        ->toBe('Replace Brake Pads · Replace Rotors');
});
