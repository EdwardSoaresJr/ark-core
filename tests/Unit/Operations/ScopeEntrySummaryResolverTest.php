<?php

use App\Ark\Operations\RepairOrders\ScopeEntrySummaryResolver;

test('scope entry summary resolver keeps rear specificity when vocabulary collapses to brakes', function () {
    expect(ScopeEntrySummaryResolver::resolve('Brakes', 'Rear brakes'))->toBe('Rear brakes')
        ->and(ScopeEntrySummaryResolver::resolve('Brakes', 'Rear Brakes'))->toBe('Rear Brakes')
        ->and(ScopeEntrySummaryResolver::resolve('Front brake service', 'Front brake service'))->toBe('Front brake service');
});

test('scope entry summary resolver keeps front specificity when vocabulary collapses', function () {
    expect(ScopeEntrySummaryResolver::resolve('Brakes', 'Front brakes'))->toBe('Front brakes');
});

test('scope entry summary resolver does not override unrelated selections', function () {
    expect(ScopeEntrySummaryResolver::resolve('Overheating', 'Rear brakes'))->toBe('Overheating')
        ->and(ScopeEntrySummaryResolver::resolve('Brake noise', 'Grinding noise'))->toBe('Brake noise');
});

test('scope entry summary resolver rejects accidental substring vocabulary like bra inside brakes', function () {
    expect(ScopeEntrySummaryResolver::resolve('bra', 'brakes'))->toBe('brakes')
        ->and(ScopeEntrySummaryResolver::resolve('bra', 'Rear brakes'))->toBe('Rear brakes')
        ->and(ScopeEntrySummaryResolver::resolve('bra', 'Rear Brakes'))->toBe('Rear Brakes')
        ->and(ScopeEntrySummaryResolver::resolve('Brakes', 'bra'))->toBe('Brakes');
});

test('scope entry summary resolver whole phrase matching requires word boundaries', function () {
    expect(ScopeEntrySummaryResolver::containsWholePhrase('rear brakes', 'brakes'))->toBeTrue()
        ->and(ScopeEntrySummaryResolver::containsWholePhrase('brakes', 'bra'))->toBeFalse()
        ->and(ScopeEntrySummaryResolver::containsWholePhrase('rear brakes', 'bra'))->toBeFalse();
});
