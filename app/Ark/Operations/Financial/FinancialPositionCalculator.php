<?php

namespace App\Ark\Operations\Financial;

use App\Ark\Operations\Documents\EstimateDocument;
use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Support\Collection;

/**
 * Sole orchestrator of Estimate + Ledger + Coverage(0) + Invoice(if exists)
 * for Financial Position. GET-pure: never mutates.
 */
final class FinancialPositionCalculator
{
    public function __construct(
        private readonly EstimateTotalsCalculator $totals,
    ) {}

    public function for(RepairOrder $repairOrder): FinancialPositionProjection
    {
        $invoice = $this->issuedInvoice($repairOrder);
        $entries = $this->activeEntries($repairOrder);

        $coverageCents = 0; // F7 — keep interface; return zero.

        if ($invoice !== null) {
            $approvedWorkCents = InvoiceSnapshotBuilder::invoiceTotalCents($invoice->snapshot_json ?? []);
            $contractSource = FinancialContractSource::Invoice;
            $depositsCents = $this->sumEntries($entries, [LedgerEntryType::Deposit]);
            $paymentsCents = $this->sumEntries($entries, [LedgerEntryType::Payment]);
            $creditsCents = $this->sumEntries($entries, [LedgerEntryType::StoreCreditApplication]);
            $writeOffsCents = $this->sumEntries($entries, [LedgerEntryType::WriteOff]);
            $adjustmentsCents = $this->sumEntries($entries, [LedgerEntryType::Adjustment]);
            $refundsCents = $this->sumEntries($entries, [LedgerEntryType::Refund]);
        } else {
            $approvedWorkCents = $this->totals->approvedTotalsForRead($repairOrder)->totalCents();
            $contractSource = FinancialContractSource::Estimate;
            $depositsCents = $this->sumEntries($entries, [LedgerEntryType::Deposit]);
            $paymentsCents = $this->sumEntries($entries, [LedgerEntryType::Payment]);
            $creditsCents = $this->sumEntries($entries, [LedgerEntryType::StoreCreditApplication]);
            $writeOffsCents = $this->sumEntries($entries, [LedgerEntryType::WriteOff]);
            $adjustmentsCents = $this->sumEntries($entries, [LedgerEntryType::Adjustment]);
            $refundsCents = $this->sumEntries($entries, [LedgerEntryType::Refund]);
        }

        $customerResponsibilityCents = max(0, $approvedWorkCents - $coverageCents);

        $customerOwesTodayCents = max(
            0,
            $approvedWorkCents
            - $coverageCents
            - $depositsCents
            - $paymentsCents
            - $writeOffsCents
            - $creditsCents
            + $adjustmentsCents
            + $refundsCents,
        );

        return new FinancialPositionProjection(
            contractSource: $contractSource,
            approvedWorkCents: $approvedWorkCents,
            coverageCents: $coverageCents,
            customerResponsibilityCents: $customerResponsibilityCents,
            depositsCents: $depositsCents,
            paymentsCents: $paymentsCents,
            creditsCents: $creditsCents,
            customerOwesTodayCents: $customerOwesTodayCents,
        );
    }

    private function issuedInvoice(RepairOrder $repairOrder): ?EstimateDocument
    {
        return EstimateDocument::query()
            ->where('repair_order_id', $repairOrder->id)
            ->where('document_type', FinancialDocumentType::Invoice->value)
            ->where('status', '!=', InvoiceStatus::Voided->value)
            ->first();
    }

    /**
     * @return Collection<int, RepairOrderLedgerEntry>
     */
    private function activeEntries(RepairOrder $repairOrder): Collection
    {
        return RepairOrderLedgerEntry::query()
            ->where('repair_order_id', $repairOrder->id)
            ->active()
            ->get();
    }

    /**
     * @param  Collection<int, RepairOrderLedgerEntry>  $entries
     * @param  list<LedgerEntryType>  $types
     */
    private function sumEntries(Collection $entries, array $types): int
    {
        return (int) $entries
            ->filter(fn (RepairOrderLedgerEntry $entry): bool => in_array($entry->entry_type, $types, true))
            ->sum('amount_cents');
    }
}
