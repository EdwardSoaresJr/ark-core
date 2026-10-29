<?php

use App\Ark\Operations\RepairOrders\RecommendationIntent;
use App\Ark\Operations\RepairOrders\RecommendationPriority;

test('diagnostic work type is not a customer priority', function () {
    expect(RecommendationIntent::Diagnostic->staffLabel())->toBe('Diagnostic')
        ->and(RecommendationIntent::Diagnostic->helpText())->toContain('testing')
        ->and(RecommendationIntent::trySemantic('diagnostic'))->toBe(RecommendationIntent::Diagnostic)
        ->and(RecommendationPriority::fromHistoricalIntent('diagnostic'))->toBeNull();
});

test('recommendation help separates priority from work type and part lines', function () {
    $items = \App\Ark\Operations\RepairOrders\RecommendationPresentation::advisorHelpOverviewItems();

    expect(collect($items)->pluck('label'))->toContain('Must Do')
        ->and(collect($items)->pluck('label'))->toContain('Diagnostic')
        ->and(collect($items)->pluck('label'))->toContain('Not for part source')
        ->and(collect($items)->pluck('label'))->toContain('Not inspection condition');
});
