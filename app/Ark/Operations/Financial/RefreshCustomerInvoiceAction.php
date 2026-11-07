<?php

namespace App\Ark\Operations\Financial;

use App\Ark\Operations\Documents\EstimateDocument;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Events\OperationalEventRecorder;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Models\User;
use RuntimeException;

/**
 * Keeps an issued invoice aligned with approved work after a pricing change, including after the
 * customer was shown it or paid part of it. Payments stay applied and the balance follows.
 *
 * A version the customer was shown, or that money was taken against, is archived as a numbered
 * revision with its stored PDF before the invoice changes. A version nobody has seen or paid
 * against since it became current is corrected in place, so repeated edits do not pile up
 * revisions.
 */
final class RefreshCustomerInvoiceAction
{
    public const SETTLED_EXPLANATION = 'A refund, store credit or write-off is recorded against this invoice, so ARK no longer updates it automatically. To revise it, void that ledger entry and then use Refresh Invoice, or settle the difference with a refund or store credit.';

    public function __construct(
        private readonly BalanceDueCalculator $balanceDue,
        private readonly InvoiceSnapshotBuilder $snapshotBuilder,
        private readonly EstimateTotalsCalculator $totalsCalculator,
        private readonly OperationalEventRecorder $events,
    ) {}

    /**
     * Refresh when an issued invoice drifts from approved work and revision is allowed.
     * Returns false when there is nothing to do or revision is blocked.
     */
    public function executeIfNeeded(RepairOrder $repairOrder, ?User $actor = null, string $trigger = 'estimate_changed'): bool
    {
        try {
            $this->execute($repairOrder, $actor, $trigger);

            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    public function execute(RepairOrder $repairOrder, ?User $actor = null, string $trigger = 'refresh_invoice'): EstimateDocument
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
        // applications need a dedicated correction path - not snapshot rewrite.
        if ($balance->writeOffsCents > 0
            || $balance->refundsAppliedCents > 0
            || $balance->creditsAppliedCents > 0
            || $balance->creditDueToStoreCreditCents > 0) {
            throw new RuntimeException(self::SETTLED_EXPLANATION);
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

        $priorRevision = $invoice->currentRevisionNumber();
        $wasPresented = $invoice->wasPresentedToCustomer();
        $archivesPrior = $wasPresented || $this->moneyMovedSince($repairOrder, $invoice);

        if ($archivesPrior) {
            $invoice->archiveCurrentSnapshotAndReplace($newSnapshot, $actor, 'approved_work_changed', [
                'trigger' => $trigger,
                'collected_cents' => $balance->collectedCents(),
                'last_ledger_entry_id' => (int) RepairOrderLedgerEntry::query()->where('repair_order_id', $repairOrder->id)->max('id'),
            ]);
        } else {
            $invoice->syncLivingSnapshot($newSnapshot);
        }

        $this->balanceDue->syncInvoiceStatus($invoice->refresh());
        $revised = $this->balanceDue->forRepairOrder($repairOrder->fresh());

        $this->events->record(
            OperationalEventName::InvoiceRevised,
            $repairOrder,
            actor: $actor,
            payload: [
                'invoice_id' => $invoice->id,
                'trigger' => $trigger,
                'revision' => $invoice->currentRevisionNumber(),
                'archived_revision' => $archivesPrior ? $priorRevision : null,
                'prior_was_presented' => $wasPresented,
                'prior_total_cents' => $balance->invoiceTotalCents,
                'new_total_cents' => $revised->invoiceTotalCents,
                'collected_cents' => $revised->collectedCents(),
                'balance_due_cents' => $revised->balanceDueCents,
                'overpaid_cents' => $revised->overpaidCents,
            ],
        );

        return $invoice;
    }

    /**
     * Whether money was recorded against this invoice since its current version began: after the
     * last ledger entry known when the previous version was archived.
     */
    private function moneyMovedSince(RepairOrder $repairOrder, EstimateDocument $invoice): bool
    {
        $lastArchived = $invoice->snapshot_revisions_json === null ? null : collect($invoice->snapshot_revisions_json)->last();

        return RepairOrderLedgerEntry::query()
            ->where('repair_order_id', $repairOrder->id)
            ->where('financial_document_id', $invoice->id)
            ->where('id', '>', (int) ($lastArchived['last_ledger_entry_id'] ?? 0))
            ->exists();
    }
}
