<?php

use App\Ark\Operations\RepairOrders\RecommendationIntent;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;

test('diagnostic recommendation intent uses aligned staff and customer labels', function () {
    expect(RecommendationIntent::Diagnostic->staffLabel())->toBe('Diagnostic')
        ->and(RecommendationIntent::Diagnostic->customerLabel())->toBe('Diagnostic')
        ->and(RecommendationIntent::Diagnostic->pdfGroupLabel())->toBe('Diagnostic')
        ->and(RecommendationIntent::Diagnostic->helpText())
        ->toContain('testing');
});

test('recommendation intent help overview lists statuses and misuse guardrails', function () {
    $items = RecommendationIntent::advisorHelpOverviewItems();

    expect($items)->toHaveCount(count(RecommendationIntent::cases()) + count(RecommendationIntent::advisorMisuseOverviewItems()))
        ->and($items[0]['label'])->toBe('Immediate Attention')
        ->and(collect($items)->pluck('label'))->toContain('Not for part source');
});

test('recommendation intent maps legacy stored values', function () {
    expect(RecommendationIntent::fromStored('immediate_attention'))->toBe(RecommendationIntent::ImmediateAttention)
        ->and(RecommendationIntent::fromStored('maintenance'))->toBe(RecommendationIntent::Maintenance)
        ->and(RecommendationIntent::fromStored('plan_soon'))->toBe(RecommendationIntent::PlanSoon)
        ->and(RecommendationIntent::fromStored('information_only'))->toBe(RecommendationIntent::InformationOnly)
        ->and(RecommendationIntent::fromStored('diagnostic'))->toBe(RecommendationIntent::Diagnostic);
});

test('recommendation intent exposes authoritative category accent palette', function () {
    expect(RecommendationIntent::ImmediateAttention->accentColor())->toBe('#9f1239')
        ->and(RecommendationIntent::Diagnostic->accentColor())->toBe('#0f766e')
        ->and(RecommendationIntent::RepairVerification->accentColor())->toBe('#7c3aed')
        ->and(RecommendationIntent::Maintenance->accentColor())->toBe('#b45309')
        ->and(RecommendationIntent::PlanSoon->accentColor())->toBe('#4338ca')
        ->and(RecommendationIntent::InformationOnly->accentColor())->toBe('#0369a1')
        ->and(RecommendationIntent::ImmediateAttention->worksheetScopeClass())->toBe('ops-worksheet-concern--intent-immediate_attention')
        ->and(RecommendationIntent::Maintenance->pdfScopeClass())->toBe('concern--intent-maintenance');
});

test('recommendation intent sorts concerns by priority without group wrappers', function () {
    $immediate = new RepairOrderConcern([
        'summary' => 'Brakes',
        'recommendation_intent' => 'immediate_attention',
        'position' => 2,
    ]);
    $diagnostic = new RepairOrderConcern([
        'summary' => 'Misfire',
        'recommendation_intent' => 'diagnostic',
        'position' => 1,
    ]);
    $planSoon = new RepairOrderConcern([
        'summary' => 'Alignment',
        'recommendation_intent' => 'plan_soon',
        'position' => 3,
    ]);

    $entries = RecommendationIntent::displayEntriesForModels(collect([$immediate, $planSoon, $diagnostic]));

    expect($entries)->toHaveCount(3)
        ->and($entries[0]['type'])->toBe('concern')
        ->and($entries[0]['concern']->summary)->toBe('Misfire')
        ->and($entries[1]['concern']->summary)->toBe('Brakes')
        ->and($entries[2]['concern']->summary)->toBe('Alignment')
        ->and(RecommendationIntent::pdfGroupOrder()[RecommendationIntent::Diagnostic->value])->toBe(0);
});

test('recommendation intent leaves a single concern ungrouped', function () {
    $concern = new RepairOrderConcern([
        'summary' => 'Oil change',
        'recommendation_intent' => RecommendationIntent::Maintenance,
        'position' => 1,
    ]);

    $entries = RecommendationIntent::displayEntriesForModels(collect([$concern]));

    expect($entries)->toHaveCount(1)
        ->and($entries[0]['type'])->toBe('concern');
});

test('worksheet pin sort key follows full priority order', function () {
    expect(RecommendationIntent::Diagnostic->worksheetPinSortKey())->toBe(0)
        ->and(RecommendationIntent::ImmediateAttention->worksheetPinSortKey())->toBe(1)
        ->and(RecommendationIntent::Maintenance->worksheetPinSortKey())->toBe(3)
        ->and(RecommendationIntent::PlanSoon->worksheetPinSortKey())->toBe(4);
});

test('sorted snapshot concerns put diagnostic first and keep types together', function () {
    $sorted = RecommendationIntent::sortedSnapshotConcerns([
        ['summary' => 'Alignment', 'recommendation_intent' => 'plan_soon', 'position' => 1],
        ['summary' => 'Inspection', 'recommendation_intent' => 'diagnostic', 'position' => 9],
        ['summary' => 'Oil', 'recommendation_intent' => 'maintenance', 'position' => 2],
        ['summary' => 'Battery', 'recommendation_intent' => 'immediate_attention', 'position' => 3],
        ['summary' => 'Fluid', 'recommendation_intent' => 'maintenance', 'position' => 1],
    ]);

    expect($sorted->pluck('summary')->all())->toBe([
        'Inspection',
        'Battery',
        'Fluid',
        'Oil',
        'Alignment',
    ]);
});
