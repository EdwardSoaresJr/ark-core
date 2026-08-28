<?php

namespace App\Console\Commands;

use App\Ark\Operations\Communications\CommunicationsFirstResponseMedian;
use Illuminate\Console\Command;

class CommunicationsFirstResponseMedianCommand extends Command
{
    protected $signature = 'ark:communications:first-response-median {--days=30 : Lookback window in days}';

    protected $description = 'Report median minutes from website lead submit to first advisor outbound (sprint KPI notebook)';

    public function handle(CommunicationsFirstResponseMedian $median): int
    {
        $result = $median->forWebsiteLeads(now()->subDays((int) $this->option('days')));

        $this->info('Website lead first-response median (minutes)');
        $this->line('Sample size: '.$result['sample_size']);

        if ($result['median_minutes'] === null) {
            $this->warn('No contacted website leads in window.');

            return self::SUCCESS;
        }

        $this->line('Median: '.$result['median_minutes'].' min');
        $this->line('P90: '.($result['p90_minutes'] ?? 'n/a').' min');
        $this->line('Sprint target: ≤ 12 minutes median');

        return self::SUCCESS;
    }
}
