<?php

use App\Ark\Operations\RepairOrders\ScopeEntryKind;

test('scope entry kind infers customer requested for plain service language', function (): void {
    expect(ScopeEntryKind::inferFromSummary('I need front brakes'))->toBe(ScopeEntryKind::CustomerRequested)
        ->and(ScopeEntryKind::inferFromSummary('Oil change'))->toBe(ScopeEntryKind::CustomerRequested)
        ->and(ScopeEntryKind::inferFromSummary('Front brake service'))->toBe(ScopeEntryKind::CustomerRequested);
});

test('scope entry kind infers customer concern for symptom language', function (): void {
    expect(ScopeEntryKind::inferFromSummary('Brake noise'))->toBe(ScopeEntryKind::CustomerConcern)
        ->and(ScopeEntryKind::inferFromSummary('Overheating in traffic'))->toBe(ScopeEntryKind::CustomerConcern)
        ->and(ScopeEntryKind::inferFromSummary('Check engine light'))->toBe(ScopeEntryKind::CustomerConcern)
        ->and(ScopeEntryKind::inferFromSummary('sounds like marbles under the hood'))->toBe(ScopeEntryKind::CustomerConcern);
});

test('scope entry kind infers warranty and diagnostic language', function (): void {
    expect(ScopeEntryKind::inferFromSummary('Recheck coolant leak after repair'))->toBe(ScopeEntryKind::WarrantyRecheck)
        ->and(ScopeEntryKind::inferFromSummary('No start intermittent'))->toBe(ScopeEntryKind::Diagnostic);
});
