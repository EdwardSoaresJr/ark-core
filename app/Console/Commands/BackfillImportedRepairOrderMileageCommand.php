<?php

namespace App\Console\Commands;

use App\Ark\Import\DatabaseLegacyArkSmsReader;
use App\Ark\Import\LegacyArkSmsValueMapper;
use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Console\Command;

class BackfillImportedRepairOrderMileageCommand extends Command
{
    protected $signature = 'ark:backfill-imported-repair-order-mileage
        {--dry-run : Show changes without saving}';

    protected $description = 'Backfill mileage_in and mileage_out on imported repair orders from legacy ARK-SMS.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $mapper = new LegacyArkSmsValueMapper;

        try {
            $reader = new DatabaseLegacyArkSmsReader;
        } catch (\Throwable $exception) {
            $this->error('Cannot connect to legacy database: '.$exception->getMessage());

            return self::FAILURE;
        }

        $legacyById = [];

        foreach ($reader->repairOrders() as $legacy) {
            $legacyById[(int) $legacy['id']] = $legacy;
        }

        $updated = 0;

        RepairOrder::query()
            ->whereColumn('repair_order_id', '!=', 'id')
            ->orderBy('id')
            ->each(function (RepairOrder $repairOrder) use ($legacyById, $mapper, $dryRun, &$updated): void {
                $legacyId = (int) $repairOrder->repair_order_id;
                $legacy = $legacyById[$legacyId] ?? null;

                if ($legacy === null) {
                    $this->warn("RO #{$repairOrder->repair_order_id}: legacy {$legacyId} not found.");

                    return;
                }

                $mileage = $mapper->repairOrderMileage($legacy);

                if (
                    $repairOrder->mileage_in === $mileage['mileage_in']
                    && $repairOrder->mileage_out === $mileage['mileage_out']
                ) {
                    return;
                }

                if ($mileage['mileage_in'] === null && $mileage['mileage_out'] === null) {
                    return;
                }

                $updated++;

                if ($dryRun) {
                    $this->line("RO #{$repairOrder->repair_order_id} (legacy {$legacyId}): ".json_encode($mileage));

                    return;
                }

                $repairOrder->forceFill($mileage)->save();
            });

        $this->info(($dryRun ? 'Would update' : 'Updated')." {$updated} imported repair orders.");

        return self::SUCCESS;
    }
}
