<?php

namespace App\Ark\Growth\Http\Controllers;

use App\Ark\Growth\Projections\JourneyExplorerProjection;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class JourneyExplorerController
{
    public function __invoke(Request $request, JourneyExplorerProjection $projection): View
    {
        $queryType = (string) $request->query('q', 'high_revenue_repairs');
        $threshold = (int) $request->query('threshold', 2500) * 100;
        $keyword = (string) $request->query('keyword', '');
        $minViews = (int) $request->query('min_views', 5);
        $limit = (int) config('growth.journey_explorer.default_limit', 50);

        return view('growth.journey-explorer', [
            'catalog' => $projection->queryCatalog(),
            'queryType' => $queryType,
            'threshold' => $threshold,
            'keyword' => $keyword,
            'minViews' => $minViews,
            'result' => $projection->resolve($queryType, [
                'threshold_cents' => $threshold,
                'keyword' => $keyword,
                'min_views' => $minViews,
            ], $limit),
        ]);
    }
}
