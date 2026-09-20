<?php

namespace App\Console\Commands;

use App\Ark\Import\DatabaseLegacyArkSmsReader;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use Illuminate\Console\Command;

class BackfillImportedConcernDispositionsCommand extends Command
{
    protected $signature = 'ark:backfill-imported-concern-dispositions
        {--dry-run : Show changes without saving}';

    protected $description = 'Reconcile imported concern dispositions from legacy concern_status (published → approved).';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        try {
            $reader = new DatabaseLegacyArkSmsReader;
        } catch (\Throwable $exception) {
            $this->error('Cannot connect to legacy database: '.$exception->getMessage());

            return self::FAILURE;
        }

        $legacyByConcernId = [];

        foreach ($reader->repairOrders() as $legacyRepairOrder) {
            $legacyRepairOrderId = (int) ($legacyRepairOrder['id'] ?? 0);

            if ($legacyRepairOrderId <= 0) {
                continue;
            }

            foreach ($reader->concernsForRepairOrder($legacyRepairOrderId) as $legacyConcern) {
                $legacyConcernId = (int) ($legacyConcern['id'] ?? 0);

                if ($legacyConcernId <= 0) {
                    continue;
                }

                $legacyByConcernId[$legacyConcernId] = strtolower(trim((string) ($legacyConcern['disposition'] ?? '')));
            }
        }

        $updated = 0;

        RepairOrderConcern::query()
            ->whereNotNull('legacy_arksms_concern_id')
            ->each(function (RepairOrderConcern $concern) use ($legacyByConcernId, $dryRun, &$updated): void {
                $legacyStatus = $legacyByConcernId[(int) $concern->legacy_arksms_concern_id] ?? null;

                if ($legacyStatus === null) {
                    return;
                }

                $target = match ($legacyStatus) {
                    'approved', 'authorized', 'paid', 'published' => RepairOrderConcernDisposition::Approved,
                    'deferred', 'deferred_maintenance' => RepairOrderConcernDisposition::Deferred,
                    'declined', 'rejected' => RepairOrderConcernDisposition::Declined,
                    default => RepairOrderConcernDisposition::Recommended,
                };

                if ($concern->disposition === $target) {
                    return;
                }

                $updated++;

                if ($dryRun) {
                    $this->line("Concern #{$concern->id}: {$concern->disposition->value} → {$target->value} (legacy {$legacyStatus})");

                    return;
                }

                $concern->forceFill(['disposition' => $target])->save();
            });

        $this->info(($dryRun ? 'Would update' : 'Updated')." {$updated} imported concerns.");

        return self::SUCCESS;
    }
}
