<?php

namespace App\Console\Commands;

use App\Ark\Import\DatabaseLegacyArkSmsReader;
use App\Ark\Import\LegacyArkSmsValueMapper;
use App\Ark\Import\LegacyImportReport;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use Illuminate\Console\Command;

class BackfillImportedSubletLineTypesCommand extends Command
{
    protected $signature = 'ark:backfill-imported-sublet-line-types
        {--dry-run : Show changes without saving}';

    protected $description = 'Correct imported repair order lines that were stored as notes when legacy type was sublet or service.';

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

        $updated = 0;

        foreach ($reader->repairOrders() as $legacyRepairOrder) {
            $legacyRepairOrderId = (int) ($legacyRepairOrder['id'] ?? 0);

            if ($legacyRepairOrderId <= 0) {
                continue;
            }

            foreach ($reader->linesForRepairOrder($legacyRepairOrderId) as $legacyLine) {
                $legacyLineId = (int) ($legacyLine['id'] ?? 0);

                if ($legacyLineId <= 0) {
                    continue;
                }

                $expectedType = $mapper->mapLineType(
                    $legacyLine['type'] ?? null,
                    $report,
                    "Line {$legacyLineId}",
                );

                if ($expectedType !== RepairOrderLineType::Sublet) {
                    continue;
                }

                $line = RepairOrderLine::query()
                    ->where('legacy_arksms_line_id', $legacyLineId)
                    ->first();

                if ($line === null || $line->type === RepairOrderLineType::Sublet) {
                    continue;
                }

                $updated++;

                if ($dryRun) {
                    $this->line("Line #{$line->id} (legacy {$legacyLineId}): {$line->type->value} → sublet");

                    continue;
                }

                $line->forceFill(['type' => RepairOrderLineType::Sublet->value])->save();
            }
        }

        $this->info(($dryRun ? 'Would update' : 'Updated')." {$updated} imported lines.");

        return self::SUCCESS;
    }
}
