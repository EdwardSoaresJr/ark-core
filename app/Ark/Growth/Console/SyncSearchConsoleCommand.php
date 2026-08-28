<?php

namespace App\Ark\Growth\Console;

use App\Ark\Growth\Maintenance\GrowthMaintenancePipeline;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

final class SyncSearchConsoleCommand extends Command
{
    protected $signature = 'growth:sync-search-console {--date= : Report date (Y-m-d). Defaults to yesterday in shop timezone.}';

    protected $description = '[Maintenance] Import Search Console query and landing page metrics as immutable daily snapshots';

    public function handle(GrowthMaintenancePipeline $pipeline): int
    {
        $date = filled($this->option('date'))
            ? Carbon::parse((string) $this->option('date'))->startOfDay()
            : now()->subDay()->startOfDay();

        $pipeline->syncSearchConsole($date);
        $pipeline->recalculateOpportunities();

        $task = app(\App\Ark\Growth\Maintenance\GrowthSyncTaskRecorder::class)
            ->lastRun(\App\Ark\Growth\Maintenance\GrowthSyncTaskKey::SearchConsole);

        if ($task === null || $task->status === \App\Ark\Growth\Maintenance\GrowthSyncTaskStatus::Failed) {
            $this->error($task?->last_message ?? 'Search Console sync failed.');

            return self::FAILURE;
        }

        $this->info($task->last_message ?? 'Search Console synchronized.');

        return self::SUCCESS;
    }
}
