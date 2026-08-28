<?php

namespace App\Console\Commands;

use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusCatalog;
use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusCatalogDefaults;
use Illuminate\Console\Command;

class SyncRepairOrderStatusCatalogCommand extends Command
{
    protected $signature = 'repair-orders:sync-status-catalog';

    protected $description = 'Sync repair order status catalog defaults (names, boards, transitions).';

    public function handle(RepairOrderStatusCatalog $catalog): int
    {
        RepairOrderStatusCatalogDefaults::sync($catalog);

        $this->info('Status catalog synced from ARK defaults.');

        return self::SUCCESS;
    }
}
