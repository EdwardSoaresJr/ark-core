<?php

namespace App\Ark\Growth\Http\Controllers;

use App\Ark\Growth\Projections\GrowthDashboardProjection;
use App\Ark\Growth\Projections\GrowthRevenueHeatmapProjection;
use Illuminate\View\View;

final class GrowthDashboardController
{
    public function __invoke(
        GrowthDashboardProjection $projection,
        GrowthRevenueHeatmapProjection $heatmap,
    ): View {
        return view('growth.dashboard', [
            'dashboard' => $projection->resolve(),
            'heatmap' => $heatmap->resolve(),
        ]);
    }
}
