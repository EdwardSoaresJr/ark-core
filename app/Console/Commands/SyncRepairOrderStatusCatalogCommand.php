<?php

namespace App\Console\Commands;

use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusCatalog;
use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusCatalogDefaults;
use Illuminate\Console\Command;

class SyncRepairOrderStatusCatalogCommand extends Command
{
    protected $signature = 'repair-orders:sync-status-catalog {--if-empty : Seed only when no statuses exist}';

    protected $description = 'Sync repair order status catalog defaults (names, boards, transitions).';

    public function handle(RepairOrderStatusCatalog $catalog): int
    {
        if ($this->option('if-empty') && $catalog->isBooted()) {
            $this->info('Status catalog already present; skipping.');

            return self::SUCCESS;
        }

        RepairOrderStatusCatalogDefaults::sync($catalog);

        $this->info('Status catalog synced from ARK defaults.');

        return self::SUCCESS;
    }
}
