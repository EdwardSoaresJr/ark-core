<?php

namespace App\Ark\Growth\Console;

use App\Ark\Growth\Integrations\GoogleBusinessProfileSyncService;
use App\Ark\Growth\Maintenance\GrowthSyncTaskKey;
use App\Ark\Growth\Maintenance\GrowthSyncTaskRecorder;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

final class SyncBusinessProfileCommand extends Command
{
    protected $signature = 'growth:sync-business-profile {--date= : Report date (Y-m-d). Defaults to yesterday.}';

    protected $description = '[Maintenance] Synchronize Google Business Profile daily metrics';

    public function handle(GoogleBusinessProfileSyncService $sync): int
    {
        $date = filled($this->option('date'))
            ? Carbon::parse((string) $this->option('date'))->startOfDay()
            : now()->subDay()->startOfDay();

        $sync->sync($date);

        $task = app(GrowthSyncTaskRecorder::class)
            ->lastRun(GrowthSyncTaskKey::GoogleBusinessProfile);

        $this->line($task?->last_message ?? 'Google Business Profile sync finished.');

        return self::SUCCESS;
    }
}
