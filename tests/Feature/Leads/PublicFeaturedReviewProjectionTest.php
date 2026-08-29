<?php

use App\Ark\Operations\Leads\Public\PublicFeaturedReviewProjection;

test('featured review matches page theme when possible', function (): void {
    $reviews = [
        ['quote' => 'Great brake work — pads measured and explained.', 'attribution' => 'Alex'],
        ['quote' => 'The most trustworthy shop in town.', 'attribution' => 'Bradley'],
        ['quote' => 'They tracked down an overheating issue another shop missed.', 'attribution' => 'Sam'],
    ];

    $match = PublicFeaturedReviewProjection::forPage('engine-overheating', $reviews);

    expect($match['quote'])->toContain('overheating');
});

test('featured review rotates by page slug when no theme match exists', function (): void {
    $reviews = [
        ['quote' => 'Review A', 'attribution' => 'A'],
        ['quote' => 'Review B', 'attribution' => 'B'],
        ['quote' => 'Review C', 'attribution' => 'C'],
    ];

    $a = PublicFeaturedReviewProjection::forPage('page-rotates-a', $reviews);
    $b = PublicFeaturedReviewProjection::forPage('page-rotates-b', $reviews);

    expect($a)->not->toBe($b);
});

test('home social proof picks randomly from the review list', function (): void {
    $reviews = [
        ['quote' => 'Review A', 'attribution' => 'A'],
        ['quote' => 'Review B', 'attribution' => 'B'],
        ['quote' => 'Review C', 'attribution' => 'C'],
        ['quote' => 'Review D', 'attribution' => 'D'],
    ];

    $seen = [];

    for ($i = 0; $i < 40; $i++) {
        $pick = PublicFeaturedReviewProjection::rotating($reviews);
        expect($pick)->not->toBeNull();
        $seen[$pick['attribution']] = true;
    }

    expect(count($seen))->toBeGreaterThan(1);
});

test('home social proof has no fixed attribution name lock', function (): void {
    $reviews = [
        ['quote' => 'Clean shop.', 'attribution' => 'Alex'],
        ['quote' => 'Exceptionally meticulous and methodical in approach.', 'attribution' => 'Sample Reviewer'],
    ];

    $seen = [];

    for ($i = 0; $i < 30; $i++) {
        $pick = PublicFeaturedReviewProjection::rotating($reviews);
        $seen[$pick['attribution']] = true;
    }

    expect($seen)->toHaveKey('Alex')
        ->and($seen)->toHaveKey('Greg Powell');
});

test('homepage prefers diagnosis-proof featured review', function (): void {
    $reviews = [
        ['quote' => 'The most trustworthy shop in town.', 'attribution' => 'Bradley'],
        ['quote' => 'Expecting the worst but they found it only needed a proper trans service that another shop left underfilled.', 'attribution' => 'Richard'],
        ['quote' => 'Clean shop and friendly staff.', 'attribution' => 'Alex'],
    ];

    $match = PublicFeaturedReviewProjection::forPage('homepage', $reviews);

    expect($match['quote'])->toContain('another shop');
});

test('transmission page prefers transmission themed review', function (): void {
    $reviews = [
        ['quote' => 'Edward found a proper trans service issue another shop missed.', 'attribution' => 'Richard'],
        ['quote' => 'The most trustworthy shop in town.', 'attribution' => 'Bradley'],
    ];

    $match = PublicFeaturedReviewProjection::forPage('transmission-slipping', $reviews);

    expect($match['quote'])->toContain('trans');
});
