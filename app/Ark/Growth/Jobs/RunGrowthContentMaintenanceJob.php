<?php

namespace App\Ark\Growth\Jobs;

use App\Ark\Growth\Maintenance\GrowthMaintenancePipeline;
use App\Ark\Growth\Models\GrowthOpportunity;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class RunGrowthContentMaintenanceJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly int $opportunityId,
    ) {}

    public function handle(GrowthMaintenancePipeline $pipeline): void
    {
        $opportunity = GrowthOpportunity::query()->find($this->opportunityId);

        if ($opportunity === null) {
            return;
        }

        $pipeline->runOnContentChange($opportunity);
    }
}
