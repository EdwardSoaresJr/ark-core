<?php

namespace App\Ark\Growth\Integrations;

use App\Ark\Growth\Models\GrowthLandingPageMetric;
use App\Ark\Growth\Models\GrowthSearchQuery;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class SearchMetricsAggregator
{
    public function lookbackDays(): int
    {
        return (int) config('growth.opportunities.lookback_days', 28);
    }

    /**
     * @return Collection<int, object{query: string, clicks: int, impressions: int, ctr: float, position: float|null}>
     */
    public function aggregatedQueries(?Carbon $through = null): Collection
    {
        $through ??= now();
        $from = $through->copy()->subDays($this->lookbackDays())->toDateString();

        return GrowthSearchQuery::query()
            ->select([
                'query',
                DB::raw('SUM(clicks) as clicks'),
                DB::raw('SUM(impressions) as impressions'),
                DB::raw('CASE WHEN SUM(impressions) > 0 THEN SUM(clicks) / SUM(impressions) ELSE 0 END as ctr'),
                DB::raw('AVG(position) as position'),
            ])
            ->whereDate('report_date', '>=', $from)
            ->whereDate('report_date', '<=', $through->toDateString())
            ->groupBy('query')
            ->orderByDesc('impressions')
            ->get();
    }

    /**
     * @return Collection<int, object{path: string, clicks: int, impressions: int, ctr: float, position: float|null}>
     */
    public function aggregatedLandingPages(?Carbon $through = null): Collection
    {
        $through ??= now();
        $from = $through->copy()->subDays($this->lookbackDays())->toDateString();

        return GrowthLandingPageMetric::query()
            ->select([
                'path',
                DB::raw('SUM(clicks) as clicks'),
                DB::raw('SUM(impressions) as impressions'),
                DB::raw('CASE WHEN SUM(impressions) > 0 THEN SUM(clicks) / SUM(impressions) ELSE 0 END as ctr'),
                DB::raw('AVG(position) as position'),
            ])
            ->whereDate('report_date', '>=', $from)
            ->whereDate('report_date', '<=', $through->toDateString())
            ->groupBy('path')
            ->orderByDesc('impressions')
            ->get();
    }
}
