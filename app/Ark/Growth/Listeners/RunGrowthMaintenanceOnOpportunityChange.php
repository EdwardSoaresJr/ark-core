<?php

namespace App\Ark\Growth\Listeners;

use App\Ark\Growth\Events\GrowthOpportunityContentUpdated;
use App\Ark\Growth\Events\GrowthOpportunityPublished;
use App\Ark\Growth\Jobs\RunGrowthContentMaintenanceJob;
use App\Ark\Growth\Jobs\RunGrowthPublishMaintenanceJob;

final class RunGrowthMaintenanceOnOpportunityChange
{
    public function handlePublished(GrowthOpportunityPublished $event): void
    {
        RunGrowthPublishMaintenanceJob::dispatch($event->opportunity->id);
    }

    public function handleContentUpdated(GrowthOpportunityContentUpdated $event): void
    {
        RunGrowthContentMaintenanceJob::dispatch($event->opportunity->id);
    }
}
