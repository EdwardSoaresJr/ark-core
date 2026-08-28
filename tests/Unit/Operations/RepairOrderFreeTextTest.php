<?php

use App\Ark\Operations\RepairOrders\RepairOrderFreeText;

test('repair order free text restores ampersands mistaken as percent between words', function (): void {
    expect(RepairOrderFreeText::normalize('Front Brakes % Rotors'))->toBe('Front Brakes & Rotors')
        ->and(RepairOrderFreeText::normalize('Front Brakes &amp; Rotors'))->toBe('Front Brakes & Rotors')
        ->and(RepairOrderFreeText::normalize('Pads 50% worn'))->toBe('Pads 50% worn');
});

test('repair order free text normalizes stored estimate snapshots', function (): void {
    $snapshot = RepairOrderFreeText::normalizeSnapshot([
        'concerns' => [[
            'summary' => 'Front Brakes % Rotors',
            'work_groups' => [[
                'title' => 'Front Brakes % Rotors',
                'lines' => [[
                    'description' => 'Front Brakes % Rotors',
                ]],
            ]],
            'lines' => [],
        ]],
    ]);

    expect($snapshot['concerns'][0]['summary'])->toBe('Front Brakes & Rotors')
        ->and($snapshot['concerns'][0]['work_groups'][0]['title'])->toBe('Front Brakes & Rotors')
        ->and($snapshot['concerns'][0]['work_groups'][0]['lines'][0]['description'])->toBe('Front Brakes & Rotors');
});
