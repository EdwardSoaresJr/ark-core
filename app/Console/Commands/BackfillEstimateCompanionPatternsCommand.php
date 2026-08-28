<?php

namespace App\Console\Commands;

use App\Ark\Operations\RepairOrders\BackfillEstimateCompanionPatternsAction;
use App\Ark\Operations\RepairOrders\EstimateCompanionPattern;
use Illuminate\Console\Command;

class BackfillEstimateCompanionPatternsCommand extends Command
{
    protected $signature = 'ark:estimate-companions:backfill
        {--fresh : Wipe catalog, re-seed timing fluids, then learn from closed/posted tickets}
        {--limit= : Max repair orders to scan}
        {--dry-run : Show counts without writing}';

    protected $description = 'Learn estimate companion patterns from closed and posted repair orders.';

    public function handle(BackfillEstimateCompanionPatternsAction $backfill): int
    {
        $fresh = (bool) $this->option('fresh');
        $dryRun = (bool) $this->option('dry-run');
        $limitOption = $this->option('limit');
        $limit = filled($limitOption) ? max(1, (int) $limitOption) : null;

        if ($dryRun) {
            $candidates = $backfill->candidateCount($limit);
            $this->info("Dry run: would scan up to {$candidates} closed/posted tickets with labor+part.");
            $this->line('Patterns now: '.EstimateCompanionPattern::query()->count());
            $this->line('Fresh reset: '.($fresh ? 'yes' : 'no'));

            return self::SUCCESS;
        }

        if ($fresh) {
            $this->warn('Fresh: wiping observed companions and re-seeding timing oil/coolant.');
        }

        $result = $backfill->execute(fresh: $fresh, limit: $limit);

        $this->info("Scanned {$result['scanned']} · ingested {$result['ingested']}.");
        $this->line("Patterns: {$result['patterns_before']} → {$result['patterns_after']}.");

        $surfaced = EstimateCompanionPattern::query()->get()->filter->shouldSurface()->count();
        $this->line("Surfaced (active warnings): {$surfaced}.");

        return self::SUCCESS;
    }
}
