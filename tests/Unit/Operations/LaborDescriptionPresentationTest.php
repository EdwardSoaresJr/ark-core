<?php

use App\Ark\Operations\Labor\CustomerLaborPresentationPresenter;
use App\Ark\Operations\RepairOrders\LaborDescriptionPresentation;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;

test('labor description presentation matches parent ignoring case and whitespace', function (): void {
    expect(LaborDescriptionPresentation::matchesParent('Replace pads', 'Replace pads'))->toBeTrue()
        ->and(LaborDescriptionPresentation::matchesParent('  replace   pads ', 'Replace Pads'))->toBeTrue()
        ->and(LaborDescriptionPresentation::matchesParent('', 'Replace pads'))->toBeTrue()
        ->and(LaborDescriptionPresentation::matchesParent('Install pads', 'Replace pads'))->toBeFalse()
        ->and(LaborDescriptionPresentation::matchesParent('Replace pads', ''))->toBeFalse();
});

test('customer labor presenter suppresses duplicate single labor description under repair title', function (): void {
    $presenter = new CustomerLaborPresentationPresenter;

    $presented = $presenter->presentLines([
        [
            'type' => RepairOrderLineType::Labor->value,
            'description' => 'Replace rear brake pads and rotors',
            'quantity' => '1.00',
        ],
    ], [
        'work_group_title' => 'Replace rear brake pads and rotors',
    ]);

    expect($presented[0]['description'])->toBe('')
        ->and($presented[0]['suppress_duplicate_description'])->toBeTrue()
        ->and($presented[0]['type_label'])->toBe('Labor');
});

test('customer labor presenter keeps descriptions when multiple labor lines exist', function (): void {
    $presenter = new CustomerLaborPresentationPresenter;

    $presented = $presenter->presentLines([
        [
            'type' => RepairOrderLineType::Labor->value,
            'description' => 'Remove Engine',
            'quantity' => '2.00',
        ],
        [
            'type' => RepairOrderLineType::Labor->value,
            'description' => 'Install Engine',
            'quantity' => '3.00',
        ],
    ], [
        'work_group_title' => 'Engine R&R',
    ]);

    expect($presented[0]['description'])->toBe('Remove Engine')
        ->and($presented[1]['description'])->toBe('Install Engine')
        ->and($presented[0]['suppress_duplicate_description'] ?? false)->toBeFalse();
});

test('customer labor presenter keeps verbatim labor when not grouped under matching title', function (): void {
    $presenter = new CustomerLaborPresentationPresenter;

    $presented = $presenter->presentLines([
        [
            'type' => RepairOrderLineType::Labor->value,
            'description' => 'R & R RADIATOR',
            'quantity' => '1.50',
        ],
    ]);

    expect($presented[0]['description'])->toBe('R & R RADIATOR')
        ->and($presented[0]['type_label'])->toBe('Labor');
});
