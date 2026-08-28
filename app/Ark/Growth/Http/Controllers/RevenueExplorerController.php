<?php

namespace App\Ark\Growth\Http\Controllers;

use App\Ark\Growth\Projections\RevenueExplorerProjection;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class RevenueExplorerController
{
    public function __invoke(Request $request, RevenueExplorerProjection $projection): View
    {
        $queryType = (string) $request->query('q', 'high_revenue_pages');
        $rawThreshold = $request->query('threshold');
        $threshold = $rawThreshold !== null
            ? (int) $rawThreshold * 100
            : (int) config('growth.revenue_explorer.default_revenue_threshold_cents', 1_000_000);
        $limit = (int) config('growth.revenue_explorer.default_limit', 50);

        return view('growth.revenue-explorer', [
            'catalog' => $projection->queryCatalog(),
            'queryType' => $queryType,
            'threshold' => $threshold,
            'result' => $projection->resolve($queryType, $threshold, $limit),
        ]);
    }
}
