<?php

use App\Ark\Operations\RepairOrders\EstimateReviewLinePresenter;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderWorkGroup;
use Illuminate\Database\Eloquent\Collection;

test('estimate review line presenter keeps labor and part descriptions verbatim', function (): void {
    $presenter = new EstimateReviewLinePresenter;

    $concern = new RepairOrderConcern([
        'summary' => 'Front brake maintenance',
    ]);

    $workGroup = new RepairOrderWorkGroup([
        'title' => 'R&R Front brake pads',
    ]);
    $workGroup->setRelation('lines', new Collection);

    $labor = new RepairOrderLine([
        'type' => RepairOrderLineType::Labor,
        'description' => 'Front brake maintenance',
        'quantity' => 1.5,
        'labor_category_name' => 'Shop Default',
    ]);

    $part = new RepairOrderLine([
        'type' => RepairOrderLineType::Part,
        'description' => 'Brakebest Select Ceramic Disc Brake Pad Set',
    ]);

    expect($presenter->description($labor, $concern, $workGroup))->toBe('Front brake maintenance')
        ->and($presenter->description($part, $concern, $workGroup))->toBe('Brakebest Select Ceramic Disc Brake Pad Set');
});

test('estimate review line presenter prefers explicit customer part description when set', function (): void {
    $presenter = new EstimateReviewLinePresenter;

    $concern = new RepairOrderConcern([
        'summary' => 'Overheating',
    ]);

    $part = new RepairOrderLine([
        'type' => RepairOrderLineType::Part,
        'description' => 'Prestone Dex-Cool 50/50 Prediluted Extended Life Antifreeze/Coolant',
        'customer_description' => 'Coolant',
    ]);

    expect($presenter->description($part, $concern))->toBe('Coolant');
});

test('estimate review suppresses matching single labor description as compact summary', function (): void {
    $presenter = new EstimateReviewLinePresenter;

    $concern = new RepairOrderConcern(['summary' => 'Brakes']);
    $labor = new RepairOrderLine([
        'type' => RepairOrderLineType::Labor,
        'description' => 'Replace rear brake pads and rotors',
        'quantity' => 1.0,
        'labor_category_name' => 'Shop Default',
    ]);
    $workGroup = new RepairOrderWorkGroup([
        'title' => 'Replace rear brake pads and rotors',
    ]);
    $workGroup->setRelation('lines', new Collection([$labor]));

    expect($presenter->description($labor, $concern, $workGroup))->toBe('1 hr · Shop Default');
});

test('estimate review keeps distinct labor descriptions when multiple labor lines exist', function (): void {
    $presenter = new EstimateReviewLinePresenter;

    $concern = new RepairOrderConcern(['summary' => 'Engine']);
    $remove = new RepairOrderLine([
        'type' => RepairOrderLineType::Labor,
        'description' => 'Remove Engine',
        'quantity' => 4.0,
    ]);
    $install = new RepairOrderLine([
        'type' => RepairOrderLineType::Labor,
        'description' => 'Install Engine',
        'quantity' => 6.0,
    ]);
    $workGroup = new RepairOrderWorkGroup([
        'title' => 'Engine Replacement',
    ]);
    $workGroup->setRelation('lines', new Collection([$remove, $install]));

    expect($presenter->description($remove, $concern, $workGroup))->toBe('Remove Engine')
        ->and($presenter->description($install, $concern, $workGroup))->toBe('Install Engine');
});
