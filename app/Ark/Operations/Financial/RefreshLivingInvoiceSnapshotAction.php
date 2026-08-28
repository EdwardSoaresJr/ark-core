<?php

namespace App\Ark\Operations\Financial;

use App\Ark\Operations\Documents\EstimateDocument;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Models\User;

/**
 * Keeps issued invoice authority aligned with living approved work until settlement.
 *
 * Deposits alone do not freeze — they reserve work, not settle the bill.
 * Once the customer has been shown an invoice (email / PDF), further approved-work
 * changes require an explicit refresh so the prior snapshot stays auditable.
 */
final class RefreshLivingInvoiceSnapshotAction
{
    public function __construct(
        private readonly BalanceDueCalculator $balanceDue,
        private readonly InvoiceSnapshotBuilder $snapshotBuilder,
        private readonly EstimateTotalsCalculator $totalsCalculator,
    ) {}

    public function syncIfEligible(RepairOrder $repairOrder, ?User $actor = null): bool
    {
        if ($repairOrder->isTerminal()) {
            return false;
        }

        $projection = $this->balanceDue->projectForRepairOrder($repairOrder);
        $invoice = $projection->invoice;

        if ($invoice === null) {
            return false;
        }

        $balance = $projection->balance;

        if ($balance->hasSettlementActivity()) {
            return false;
        }

        if ($invoice->wasPresentedToCustomer()) {
            // Customer already saw this bill — do not silently mutate.
            return false;
        }

        $newSnapshot = $this->snapshotBuilder->build($repairOrder->fresh(), $actor);
        $approvedTotalCents = $this->totalsCalculator->approvedTotalsForRead($repairOrder)->totalCents();
        $totalsMatch = $approvedTotalCents === $balance->invoiceTotalCents;
        $contentMatches = ! InvoiceSnapshotBuilder::contentDiffers(
            is_array($invoice->snapshot_json) ? $invoice->snapshot_json : [],
            $newSnapshot,
        );

        if ($totalsMatch && $contentMatches) {
            return false;
        }

        $invoice->syncLivingSnapshot($newSnapshot);

        $this->balanceDue->syncInvoiceStatus($invoice->refresh());

        return true;
    }
}
