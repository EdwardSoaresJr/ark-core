<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Cron-friendly bounded queue worker for hosts without Horizon.
 *
 * Example crontab (minimum):
 *   * * * * * php /path/to/artisan schedule:run
 *   * * * * * php /path/to/artisan ark:queue-work-bounded
 */
class ArkQueueWorkBoundedCommand extends Command
{
    protected $signature = 'ark:queue-work-bounded
        {--stop-when-empty : Stop when the queue is empty (default true)}
        {--max-time=55 : Maximum seconds to run}
        {--max-jobs=25 : Maximum jobs to process}
        {--tries=3 : Job attempts}';

    protected $description = 'Process a bounded batch of queue jobs for cron-based shared/VPS hosting.';

    public function handle(): int
    {
        $connection = (string) config('queue.default', 'database');
        if ($connection === 'sync') {
            $this->info('Queue connection is sync; nothing to drain.');

            return self::SUCCESS;
        }

        $params = [
            '--stop-when-empty' => $this->option('stop-when-empty') ?? true,
            '--max-time' => (int) $this->option('max-time'),
            '--max-jobs' => (int) $this->option('max-jobs'),
            '--tries' => (int) $this->option('tries'),
            '--no-interaction' => true,
        ];

        return $this->call('queue:work', $params);
    }
}
