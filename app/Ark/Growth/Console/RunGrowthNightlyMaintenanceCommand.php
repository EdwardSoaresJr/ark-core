<?php

namespace App\Ark\Growth\Console;

use App\Ark\Growth\Jobs\RunGrowthNightlyMaintenanceJob;
use Illuminate\Console\Command;

final class RunGrowthNightlyMaintenanceCommand extends Command
{
    protected $signature = 'growth:run-nightly-maintenance {--date= : Search Console report date (Y-m-d)} {--sync : Run synchronously instead of queueing}';

    protected $description = '[Maintenance] Run the nightly Growth pipeline — Search Console, GBP, content registry, SEO audit, opportunities';

    public function handle(): int
    {
        $date = $this->option('date');
        $job = new RunGrowthNightlyMaintenanceJob(is_string($date) && filled($date) ? $date : null);

        if ($this->option('sync')) {
            dispatch_sync($job);
            $this->info('Growth nightly maintenance completed.');
        } else {
            dispatch($job);
            $this->info('Growth nightly maintenance job queued.');
        }

        return self::SUCCESS;
    }
}
