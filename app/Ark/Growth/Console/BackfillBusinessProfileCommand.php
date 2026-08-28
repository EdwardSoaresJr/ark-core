<?php

namespace App\Ark\Growth\Console;

use App\Ark\Growth\Integrations\GoogleBusinessProfileSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

final class BackfillBusinessProfileCommand extends Command
{
    protected $signature = 'growth:backfill-business-profile
                            {--days=28 : Number of days to backfill ending at --to or yesterday}
                            {--from= : Start date (Y-m-d)}
                            {--to= : End date (Y-m-d). Defaults to yesterday.}';

    protected $description = '[Maintenance] Backfill Google Business Profile daily metrics for a date range';

    public function handle(GoogleBusinessProfileSyncService $sync): int
    {
        $end = filled($this->option('to'))
            ? Carbon::parse((string) $this->option('to'))->startOfDay()
            : now()->subDay()->startOfDay();

        $start = filled($this->option('from'))
            ? Carbon::parse((string) $this->option('from'))->startOfDay()
            : $end->copy()->subDays(max(1, (int) $this->option('days')) - 1);

        $result = $sync->backfill($start, $end);

        if ($result === null) {
            $this->error('Google Business Profile backfill did not run.');

            return self::FAILURE;
        }

        if ($result['rows'] === 0) {
            $this->warn('No Google Business Profile rows were imported for the requested range.');

            return self::SUCCESS;
        }

        $this->info("Backfilled {$result['rows']} rows across {$result['days']} days ({$result['start_date']} to {$result['end_date']}).");

        return self::SUCCESS;
    }
}
