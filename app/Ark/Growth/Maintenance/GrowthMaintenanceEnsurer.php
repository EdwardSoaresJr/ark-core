<?php

namespace App\Ark\Growth\Maintenance;

use App\Ark\Growth\Jobs\RunGrowthNightlyMaintenanceJob;
use Illuminate\Support\Facades\Cache;

/**
 * Ensures Growth maintenance runs without operator intervention.
 */
final class GrowthMaintenanceEnsurer
{
    private const LOCK_SECONDS = 300;

    public function __construct(
        private readonly GrowthSyncTaskRecorder $recorder,
    ) {}

    public function ensureCurrent(): void
    {
        if (! config('growth.maintenance.auto_ensure', true)) {
            return;
        }

        if (app()->environment('testing')) {
            return;
        }

        if (! $this->needsMaintenance()) {
            return;
        }

        Cache::lock('growth:maintenance:ensure', self::LOCK_SECONDS)->get(function (): void {
            if (! $this->needsMaintenance()) {
                return;
            }

            RunGrowthNightlyMaintenanceJob::dispatch();
        });
    }

    private function needsMaintenance(): bool
    {
        $maxAgeHours = (int) config('growth.maintenance.stale_after_hours', 26);

        return $this->recorder->isStale(GrowthSyncTaskKey::SearchConsole, $maxAgeHours)
            || $this->recorder->isStale(GrowthSyncTaskKey::GoogleBusinessProfile, $maxAgeHours)
            || $this->recorder->isStale(GrowthSyncTaskKey::PublicContent, $maxAgeHours)
            || $this->recorder->isStale(GrowthSyncTaskKey::OpportunityQueue, $maxAgeHours);
    }
}
