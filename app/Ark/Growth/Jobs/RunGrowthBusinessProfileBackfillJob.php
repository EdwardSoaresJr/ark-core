<?php

namespace App\Ark\Growth\Jobs;

use App\Ark\Growth\Integrations\GoogleBusinessProfileSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;

final class RunGrowthBusinessProfileBackfillJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly int $days = 28,
    ) {}

    public function handle(GoogleBusinessProfileSyncService $sync): void
    {
        $end = now()->subDay()->startOfDay();
        $start = $end->copy()->subDays(max(1, $this->days) - 1);

        $sync->backfill($start, $end);
    }
}
