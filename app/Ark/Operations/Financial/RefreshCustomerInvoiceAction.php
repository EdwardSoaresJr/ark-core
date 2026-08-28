<?php

namespace App\Ark\Operations\Financial;

use App\Ark\Operations\Documents\EstimateDocument;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Models\User;
use RuntimeException;

/**
 * Explicit invoice refresh after approved work changed on a customer-presented bill.
 * Archives the prior snapshot for audit, then writes living approved work.
 */
final class RefreshCustomerInvoiceAction
{
    public function __construct(
        private readonly BalanceDueCalculator $balanceDue,
        private readonly InvoiceSnapshotBuilder $snapshotBuilder,
        private readonly EstimateTotalsCalculator $totalsCalculator,
    ) {}

    /**
     * Refresh when an issued invoice drifts from approved work and revision is allowed.
     * Returns false when there is nothing to do or revision is blocked.
     */
    public function executeIfNeeded(RepairOrder $repairOrder, ?User $actor = null): bool
    {
        try {
            $this->execute($repairOrder, $actor);

            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    public function execute(RepairOrder $repairOrder, ?User $actor = null): EstimateDocument
    {
        if ($repairOrder->isTerminal()) {
            throw new RuntimeException('Closed repair orders cannot refresh invoices.');
        }

        $projection = $this->balanceDue->projectForRepairOrder($repairOrder);
        $invoice = $projection->invoice;

        if ($invoice === null) {
            throw new RuntimeException('Generate the final invoice before refreshing it.');
        }

        $balance = $projection->balance;

        // Payments/deposits stay applied after refresh. Write-offs, refunds, and store-credit
        // applications need a dedicated correction path — not snapshot rewrite.
        if ($balance->writeOffsCents > 0
            || $balance->refundsAppliedCents > 0
            || $balance->creditsAppliedCents > 0) {
            throw new RuntimeException('This invoice has write-off, refund, or credit activity. Use credit memo, adjustment, or refund workflows.');
        }

        $newSnapshot = $this->snapshotBuilder->build($repairOrder->fresh(), $actor);
        $approvedTotalCents = $this->totalsCalculator->approvedTotalsForRead($repairOrder)->totalCents();
        $totalsMatch = $approvedTotalCents === $balance->invoiceTotalCents;
        $contentMatches = ! InvoiceSnapshotBuilder::contentDiffers(
            is_array($invoice->snapshot_json) ? $invoice->snapshot_json : [],
            $newSnapshot,
        );

        if ($totalsMatch && $contentMatches) {
            throw new RuntimeException('Invoice already matches approved work.');
        }

        $invoice->archiveCurrentSnapshotAndReplace(
            $newSnapshot,
            $actor,
            reason: 'approved_work_changed',
        );

        $this->balanceDue->syncInvoiceStatus($invoice->refresh());

        return $invoice;
    }
}
