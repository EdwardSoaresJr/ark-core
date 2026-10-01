<?php

use App\Ark\Operations\Labor\CustomerLaborPresentationPresenter;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;

test('customer labor presenter keeps labor lines verbatim and labels them labor', function (): void {
    $presenter = new CustomerLaborPresentationPresenter;

    $presented = $presenter->presentLines([
        [
            'type' => RepairOrderLineType::Labor->value,
            'description' => 'R & R RADIATOR',
            'quantity' => '1.50',
            'subtotal_cents' => 24750,
            'total_cents' => 25616,
        ],
        [
            'type' => RepairOrderLineType::Labor->value,
            'description' => 'COMBUSTION TEST COOLING SYSTEM',
            'quantity' => '0.75',
            'subtotal_cents' => 12375,
            'total_cents' => 12808,
        ],
    ]);

    expect($presented)->toHaveCount(2)
        ->and($presented[0]['description'])->toBe('R & R RADIATOR')
        ->and($presented[0]['type_label'])->toBe('Labor')
        ->and($presented[1]['description'])->toBe('COMBUSTION TEST COOLING SYSTEM')
        ->and($presented[1]['type_label'])->toBe('Labor');
});

test('customer labor presenter keeps parts and labels sublet as service', function (): void {
    $presenter = new CustomerLaborPresentationPresenter;

    $presented = $presenter->presentLines([
        [
            'type' => RepairOrderLineType::Labor->value,
            'description' => 'R & R RADIATOR',
            'quantity' => '1.50',
            'subtotal_cents' => 24750,
            'total_cents' => 25616,
        ],
        [
            'type' => RepairOrderLineType::Part->value,
            'description' => '68192041AB Radiator Assembly',
            'quantity' => '1.00',
            'subtotal_cents' => 45000,
            'total_cents' => 45000,
        ],
        [
            'type' => RepairOrderLineType::Sublet->value,
            'description' => 'Wheel alignment',
            'quantity' => '1.00',
            'subtotal_cents' => 12000,
            'total_cents' => 12000,
        ],
    ]);

    expect($presented)->toHaveCount(3)
        ->and($presented[0]['type_label'])->toBe('Labor')
        ->and($presented[1]['description'])->toBe('68192041AB Radiator Assembly')
        ->and($presented[2]['description'])->toBe('Wheel alignment')
        ->and($presented[2]['type_label'])->toBe('Service');
});

test('customer labor presenter review description stays verbatim', function (): void {
    $presenter = new CustomerLaborPresentationPresenter;

    expect($presenter->reviewLineDescription([
        'description' => 'Front brake maintenance',
    ], [
        'summary' => 'Front brake maintenance',
        'work_group_title' => 'R&R Front brake pads',
    ]))->toBe('Front brake maintenance');
});

test('customer labor presenter review description blanks when matching repair title', function (): void {
    $presenter = new CustomerLaborPresentationPresenter;

    expect($presenter->reviewLineDescription([
        'type' => RepairOrderLineType::Labor->value,
        'description' => 'R&R Front brake pads',
    ], [
        'work_group_title' => 'R&R Front brake pads',
    ]))->toBe('');
});
