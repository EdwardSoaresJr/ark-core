<?php

namespace App\Console\Commands;

use App\Ark\Import\DatabaseLegacyArkSmsReader;
use App\Ark\Import\LegacyArkSmsValueMapper;
use App\Ark\Import\LegacyImportReport;
use App\Ark\Import\LegacyRepairOrderTimeline;
use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Console\Command;

class BackfillImportedRepairOrderDatesCommand extends Command
{
    protected $signature = 'ark:backfill-imported-repair-order-dates
        {--dry-run : Show changes without saving}';

    protected $description = 'Backfill opened_at and closed_at on imported repair orders from legacy ARK-SMS.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $mapper = new LegacyArkSmsValueMapper;
        $report = new LegacyImportReport;

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
            ->each(function (RepairOrder $repairOrder) use ($legacyById, $mapper, $report, $dryRun, &$updated): void {
                $legacyId = (int) $repairOrder->repair_order_id;
                $legacy = $legacyById[$legacyId] ?? null;

                if ($legacy === null) {
                    $this->warn("RO #{$repairOrder->repair_order_id}: legacy {$legacyId} not found.");

                    return;
                }

                $status = $mapper->mapRepairOrderStatus($legacy['status'] ?? null, $report);
                $openedAt = LegacyRepairOrderTimeline::openedAt($legacy);
                $closedAt = LegacyRepairOrderTimeline::closedAt(
                    $legacy,
                    $status,
                    strtolower(trim((string) ($legacy['status'] ?? ''))),
                );

                $changes = array_filter([
                    'opened_at' => $openedAt?->toDateTimeString(),
                    'closed_at' => $closedAt?->toDateTimeString(),
                ], fn (?string $value, string $key): bool => ($repairOrder->{$key}?->toDateTimeString() ?? null) !== $value, ARRAY_FILTER_USE_BOTH);

                if ($changes === []) {
                    return;
                }

                $updated++;

                if ($dryRun) {
                    $this->line("RO #{$repairOrder->repair_order_id} (legacy {$legacyId}): ".json_encode($changes));

                    return;
                }

                $repairOrder->forceFill([
                    'opened_at' => $openedAt,
                    'closed_at' => $closedAt,
                ])->save();
            });

        $this->info(($dryRun ? 'Would update' : 'Updated')." {$updated} imported repair orders.");

        return self::SUCCESS;
    }
}
