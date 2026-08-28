<?php

namespace App\Ark\Growth\Http\Controllers;

use App\Ark\Growth\Maintenance\GrowthMaintenanceEnsurer;
use App\Ark\Growth\Maintenance\GrowthSyncStatusProjection;
use App\Ark\Growth\Opportunities\OpportunityQueueProjection;
use Illuminate\Contracts\View\View;

final class GrowthOpportunityIndexController
{
    public function __invoke(
        OpportunityQueueProjection $projection,
        GrowthSyncStatusProjection $syncStatus,
        GrowthMaintenanceEnsurer $ensurer,
    ): View {
        $ensurer->ensureCurrent();

        $queue = $projection->resolve(
            (int) config('growth.opportunities.queue_limit', 5),
        );

        return view('growth.opportunities.index', [
            'queue' => $queue,
            'sync' => $syncStatus->resolve(),
        ]);
    }
}
