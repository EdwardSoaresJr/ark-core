<?php

use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernMeaningPresentation;
use App\Ark\Operations\RepairOrders\ScopeEntryKind;

test('meaning presentation keeps entry kind off the worksheet surface', function (): void {
    $concern = new RepairOrderConcern([
        'summary' => 'Overheting',
        'scope_entry_kind' => ScopeEntryKind::CustomerConcern,
    ]);

    $presentation = new RepairOrderConcernMeaningPresentation($concern);

    expect($presentation->entryKindLabel())->toBe('Customer Concern')
        ->and($presentation->showsMeaningStrip())->toBeFalse()
        ->and($presentation->customerWording())->toBeNull();
});

test('meaning presentation shows customer wording when it differs from summary', function (): void {
    $concern = new RepairOrderConcern([
        'summary' => 'Front brake service',
        'scope_entry_kind' => ScopeEntryKind::CustomerRequested,
        'customer_states' => 'I think I need front brakes.',
    ]);

    $presentation = new RepairOrderConcernMeaningPresentation($concern);

    expect($presentation->customerWording())->toBe('I think I need front brakes.')
        ->and($presentation->showsCustomerWording())->toBeTrue()
        ->and($presentation->showsMeaningStrip())->toBeTrue();
});

test('meaning presentation hides customer wording when it matches the scope title', function (): void {
    $concern = new RepairOrderConcern([
        'summary' => 'Coolant leak',
        'scope_entry_kind' => ScopeEntryKind::CustomerRequested,
        'customer_states' => 'Coolant leak',
    ]);

    $presentation = new RepairOrderConcernMeaningPresentation($concern);

    expect($presentation->showsMeaningStrip())->toBeFalse()
        ->and($presentation->customerWording())->toBeNull();
});

test('meaning presentation does not surface retired concern advisor notes', function (): void {
    $concern = new RepairOrderConcern([
        'summary' => 'General Inspection',
        'notes' => "Diagnostic\n\nTechnician: Big O tie rod finding.",
    ]);

    $presentation = new RepairOrderConcernMeaningPresentation($concern);

    expect($presentation->showsMeaningStrip())->toBeFalse();
});
