<?php

namespace App\Ark\Growth\Http\Controllers;

use App\Ark\Growth\Jobs\RunGrowthNightlyMaintenanceJob;
use Illuminate\Http\RedirectResponse;

final class GrowthMaintenanceRebuildController
{
    public function __invoke(): RedirectResponse
    {
        RunGrowthNightlyMaintenanceJob::dispatch();

        return back()->with('status', 'Growth maintenance rebuild queued. Sync status will update shortly.');
    }
}
