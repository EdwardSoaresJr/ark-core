<?php

namespace App\Console\Commands;

use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\RepairOrders\RetreatRepairOrderAfterAuthorizationRevocationAction;
use Illuminate\Console\Command;

class RetreatRepairOrdersWithoutApprovedWorkCommand extends Command
{
    protected $signature = 'ark:repair-orders:retreat-without-approved-work
        {--repair-order-id= : Limit to one shop-facing repair order number}';

    protected $description = 'Move repair orders back to waiting approval when no approved invoiceable work remains.';

    public function handle(
        EstimateTotalsCalculator $totalsCalculator,
        RetreatRepairOrderAfterAuthorizationRevocationAction $retreat,
    ): int {
        $repairOrderId = $this->option('repair-order-id');

        $query = RepairOrder::query()
            ->whereIn('status', [
                RepairOrderStatus::Approved->value,
                RepairOrderStatus::WaitingParts->value,
                RepairOrderStatus::ReadyForWork->value,
                RepairOrderStatus::InProgress->value,
                RepairOrderStatus::QualityCheck->value,
            ])
            ->with(['concerns', 'lines.concern', 'approvalEvents.revocation']);

        if (filled($repairOrderId)) {
            $query->where('repair_order_id', (int) $repairOrderId);
        }

        $retreated = 0;

        $query->orderBy('id')->chunkById(50, function ($repairOrders) use ($totalsCalculator, $retreat, &$retreated): void {
            foreach ($repairOrders as $repairOrder) {
                if ($totalsCalculator->hasApprovedInvoiceableWork($repairOrder)) {
                    continue;
                }

                $before = $repairOrder->status->value;

                $retreat->execute(
                    $repairOrder,
                    reason: 'no_approved_work_reconciliation',
                );

                $repairOrder->refresh();

                if ($repairOrder->status->value !== $before) {
                    $retreated++;
                    $this->line("RO #{$repairOrder->repair_order_id}: {$before} → {$repairOrder->status->value}");
                }
            }
        });

        $this->info("Retreated {$retreated} repair order(s) to waiting approval.");

        return self::SUCCESS;
    }
}
