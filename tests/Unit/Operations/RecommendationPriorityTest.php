<?php

use App\Ark\Operations\RepairOrders\RecommendationIntent;
use App\Ark\Operations\RepairOrders\RecommendationPresentation;
use App\Ark\Operations\RepairOrders\RecommendationPriority;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;

test('priority labels and colors come from one definition', function () {
    $must = RecommendationPresentation::priority(RecommendationPriority::MustDo);

    expect(RecommendationPriority::MustDo->staffLabel())->toBe('Must Do')
        ->and(RecommendationPriority::MustDo->customerLabel())->toBe('Must Do')
        ->and($must->accentColor())->toBe(RecommendationPriority::MustDo->accentColor())
        ->and($must->customerLabel())->toBe('Must Do')
        ->and($must->worksheetScopeClass())->toBe('ops-worksheet-concern--priority-must_do')
        ->and($must->pdfScopeClass())->toBe('concern--priority-must_do')
        ->and(RecommendationPresentation::informationOnly()->customerLabel())->toBe('Information Only')
        ->and(RecommendationPresentation::informationOnly()->sortKey())->toBe(3)
        ->and(RecommendationPriority::MustDo->sortKey())->toBe(0)
        ->and(RecommendationPriority::ShouldDo->sortKey())->toBe(1)
        ->and(RecommendationPriority::CouldDo->sortKey())->toBe(2);
});

test('work type stays separate from priority', function () {
    expect(RecommendationIntent::trySemantic('diagnostic'))->toBe(RecommendationIntent::Diagnostic)
        ->and(RecommendationIntent::trySemantic('immediate_attention'))->toBeNull()
        ->and(RecommendationIntent::trySemantic('maintenance'))->toBe(RecommendationIntent::Maintenance)
        ->and(RecommendationPriority::fromHistoricalIntent('diagnostic'))->toBeNull()
        ->and(RecommendationPriority::fromHistoricalIntent('immediate_attention'))->toBe(RecommendationPriority::MustDo)
        ->and(RecommendationPriority::fromHistoricalIntent('plan_soon'))->toBe(RecommendationPriority::ShouldDo)
        ->and(RecommendationPriority::isHistoricalInformationOnly('information_only'))->toBeTrue();
});

test('legacy urgency writes become priority and clear the work type', function () {
    $concern = new RepairOrderConcern([
        'summary' => 'Brakes',
        'recommendation_intent' => 'immediate_attention',
        'position' => 1,
    ]);

    expect($concern->recommendationPriority())->toBe(RecommendationPriority::MustDo)
        ->and($concern->recommendationIntent())->toBeNull()
        ->and($concern->isInformationOnly())->toBeFalse()
        ->and($concern->presentation()->customerLabel())->toBe('Must Do');
});

test('information only clears priority and diagnostic does not sort as urgency', function () {
    $must = new RepairOrderConcern([
        'summary' => 'Brakes',
        'recommendation_priority' => RecommendationPriority::MustDo,
        'recommendation_intent' => RecommendationIntent::Diagnostic,
        'position' => 2,
    ]);
    $diagnostic = new RepairOrderConcern([
        'summary' => 'Misfire',
        'recommendation_intent' => RecommendationIntent::Diagnostic,
        'position' => 1,
    ]);
    $info = new RepairOrderConcern([
        'summary' => 'Note',
        'recommendation_intent' => 'information_only',
        'position' => 3,
    ]);

    $sorted = RecommendationPresentation::sortedModels(collect([$diagnostic, $info, $must]));

    expect($info->isInformationOnly())->toBeTrue()
        ->and($info->recommendationPriority())->toBeNull()
        ->and($sorted->pluck('summary')->all())->toBe(['Brakes', 'Note', 'Misfire']);
});

test('issued snapshots keep the stored label', function () {
    $legacy = RecommendationPresentation::forSnapshot([
        'recommendation_intent' => 'immediate_attention',
        'recommendation_intent_label' => 'Immediate Attention',
        'recommendation_intent_customer_label' => 'Immediate Attention',
    ]);

    $current = RecommendationPresentation::forSnapshot([
        'recommendation_priority' => 'must_do',
        'information_only' => false,
        'recommendation_intent' => 'diagnostic',
        'recommendation_intent_label' => 'Diagnostic',
    ]);

    expect($legacy->customerLabel())->toBe('Immediate Attention')
        ->and($legacy->accentColor())->toBe(RecommendationPriority::MustDo->accentColor())
        ->and($current->customerLabel())->toBe('Must Do')
        ->and($current->staffLabel())->toBe('Must Do');
});
