<?php

namespace App\Console\Commands;

use App\Ark\Import\DatabaseLegacyArkSmsReader;
use App\Ark\Import\ImportedRepairOrderShopNumberBackfiller;
use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Console\Command;

class BackfillImportedRepairOrderShopNumbersCommand extends Command
{
    protected $signature = 'ark:backfill-imported-repair-order-shop-numbers
        {--dry-run : Show how many repair orders would change without saving}';

    protected $description = 'Restore legacy shop repair order numbers and reassign native ROs that were stamped with the database id.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        try {
            $reader = new DatabaseLegacyArkSmsReader;
        } catch (\Throwable $exception) {
            $this->error('Cannot connect to legacy database: '.$exception->getMessage());

            return self::FAILURE;
        }

        $result = (new ImportedRepairOrderShopNumberBackfiller($reader))->run($dryRun);

        $this->info(sprintf(
            '%s %d repair order(s): %d imported legacy match, %d native reassignment, %d already correct, %d unmatched imported.',
            $dryRun ? 'Would update' : 'Updated',
            $result['updated'],
            $result['imported'],
            $result['native'],
            $result['skipped'],
            $result['unmatched'],
        ));

        if ($result['unmatched'] > 0) {
            $this->warn('Unmatched imported repair orders still need manual review in legacy ARK-SMS.');
        }

        $this->line('Next new repair order shop number: '.RepairOrder::nextShopRepairOrderId());

        return self::SUCCESS;
    }
}
