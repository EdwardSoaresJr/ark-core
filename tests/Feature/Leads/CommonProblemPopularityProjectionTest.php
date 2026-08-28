<?php

use App\Ark\Growth\Models\GrowthLandingPageMetric;
use App\Ark\Operations\Leads\Public\CommonProblemPopularityProjection;
use App\Ark\Operations\Leads\Public\CommonProblemRegistry;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    Cache::forget('common_problem_popularity.clicks_by_slug');
});

test('common problem popularity keeps curated order when search metrics are empty', function (): void {
    $curated = CommonProblemRegistry::featuredForIndexSymptoms();

    $sorted = app(CommonProblemPopularityProjection::class)->sortByPopularity($curated);

    expect(collect($sorted)->pluck('slug')->all())
        ->toBe(collect($curated)->pluck('slug')->all());
});

test('common problem popularity sorts curated lists by search console clicks', function (): void {
    $reportDate = now()->subDays(3)->toDateString();

    GrowthLandingPageMetric::query()->create([
        'path' => '/common-problems/wheel-bearing-noise',
        'report_date' => $reportDate,
        'period_start' => $reportDate,
        'period_end' => $reportDate,
        'clicks' => 40,
        'impressions' => 400,
        'ctr' => 0.1,
        'position' => 8.0,
    ]);

    GrowthLandingPageMetric::query()->create([
        'path' => '/common-problems/check-engine-light',
        'report_date' => $reportDate,
        'period_start' => $reportDate,
        'period_end' => $reportDate,
        'clicks' => 12,
        'impressions' => 200,
        'ctr' => 0.06,
        'position' => 12.0,
    ]);

    $sorted = app(CommonProblemPopularityProjection::class)->sortByPopularity(
        CommonProblemRegistry::featuredForIndexSymptoms(),
    );

    expect($sorted[0]['slug'])->toBe('wheel-bearing-noise')
        ->and($sorted[1]['slug'])->toBe('check-engine-light');
});

test('common problems index renders popular symptoms before quieter ones', function (): void {
    $reportDate = now()->subDays(2)->toDateString();

    GrowthLandingPageMetric::query()->create([
        'path' => '/common-problems/brake-noise',
        'report_date' => $reportDate,
        'period_start' => $reportDate,
        'period_end' => $reportDate,
        'clicks' => 55,
        'impressions' => 500,
        'ctr' => 0.11,
        'position' => 6.0,
    ]);

    $html = $this->get(route('public.common-problems.index'))
        ->assertOk()
        ->getContent();

    $brakePos = strpos($html, 'Brake Noise');
    $checkEnginePos = strpos($html, 'Check Engine Light');

    expect($brakePos)->not->toBeFalse()
        ->and($checkEnginePos)->not->toBeFalse()
        ->and($brakePos)->toBeLessThan($checkEnginePos);
});
