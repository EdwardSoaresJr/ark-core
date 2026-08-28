<?php

namespace App\Ark\Operations\Leads\Public;

use App\Ark\Growth\Integrations\SearchMetricsAggregator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Reorders curated common-problem lists by Search Console popularity.
 * Projection only — rebuildable from GrowthLandingPageMetric; curated order is the fallback.
 */
final class CommonProblemPopularityProjection
{
    private const CACHE_KEY = 'common_problem_popularity.clicks_by_slug';

    private const CACHE_SECONDS = 3600;

    public function __construct(
        private readonly SearchMetricsAggregator $searchMetrics,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $problems
     * @return list<array<string, mixed>>
     */
    public function sortByPopularity(array $problems): array
    {
        if ($problems === []) {
            return [];
        }

        $scores = $this->clicksBySlug();
        $curatedOrder = array_flip(array_values(array_map(
            static fn (array $problem): string => (string) ($problem['slug'] ?? ''),
            $problems,
        )));

        return collect($problems)
            ->sort(function (array $left, array $right) use ($scores, $curatedOrder): int {
                $leftSlug = (string) ($left['slug'] ?? '');
                $rightSlug = (string) ($right['slug'] ?? '');
                $leftScore = $scores[$leftSlug] ?? 0;
                $rightScore = $scores[$rightSlug] ?? 0;

                if ($leftScore !== $rightScore) {
                    return $rightScore <=> $leftScore;
                }

                return ($curatedOrder[$leftSlug] ?? PHP_INT_MAX) <=> ($curatedOrder[$rightSlug] ?? PHP_INT_MAX);
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, int>
     */
    public function clicksBySlug(): array
    {
        if (! Schema::hasTable('growth_landing_page_metrics')) {
            return [];
        }

        return Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, function (): array {
            $scores = [];

            foreach ($this->searchMetrics->aggregatedLandingPages() as $row) {
                $path = (string) ($row->path ?? '');

                if (! str_starts_with($path, '/common-problems/')) {
                    continue;
                }

                $slug = trim(substr($path, strlen('/common-problems/')), '/');

                if ($slug === '' || str_contains($slug, '/')) {
                    continue;
                }

                $scores[$slug] = (int) ($row->clicks ?? 0);
            }

            return $scores;
        });
    }
}
