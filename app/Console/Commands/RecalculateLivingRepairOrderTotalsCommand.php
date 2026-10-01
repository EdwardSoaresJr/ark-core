<?php

namespace App\Console\Commands;

use App\Ark\Operations\Documents\EstimateDocumentService;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use Illuminate\Console\Command;

class RecalculateLivingRepairOrderTotalsCommand extends Command
{
    protected $signature = 'ark:recalculate-living-repair-order-totals
        {--refresh-documents : Rebuild living estimate document snapshots after recalculating lines}';

    protected $description = 'Recalculate tax, shop fees, and line totals on all non-closed repair orders using current shop settings.';

    public function handle(EstimateTotalsCalculator $calculator, EstimateDocumentService $documents): int
    {
        $count = $calculator->recalculateLivingRepairOrders();

        $this->info("Recalculated {$count} living repair order(s).");

        if ($this->option('refresh-documents')) {
            $refreshed = $documents->refreshOpenDocumentsForShopSettingsChange();
            $this->info("Refreshed {$refreshed} living estimate document snapshot(s).");
        }

        return self::SUCCESS;
    }
}
