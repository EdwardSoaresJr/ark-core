<?php

namespace App\Ark\Growth\Jobs;

use App\Ark\Growth\Maintenance\GrowthMaintenancePipeline;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;

final class RunGrowthNightlyMaintenanceJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly ?string $reportDate = null,
    ) {}

    public function handle(GrowthMaintenancePipeline $pipeline): void
    {
        $date = filled($this->reportDate)
            ? Carbon::parse($this->reportDate)->startOfDay()
            : null;

        $pipeline->runNightly($date);
    }
}
