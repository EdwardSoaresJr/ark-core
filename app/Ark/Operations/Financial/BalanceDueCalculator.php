<?php

namespace App\Ark\Operations\Financial;

use App\Ark\Operations\Documents\EstimateDocument;
use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Support\Collection;

final class BalanceDueCalculator
{
    public function projectForRepairOrder(RepairOrder $repairOrder): RepairOrderBalanceProjection
    {
        $invoice = $this->queryIssuedInvoice($repairOrder);
        $entries = $this->queryActiveEntries($repairOrder);

        return new RepairOrderBalanceProjection(
            balance: $this->buildBalanceResult($invoice, $entries),
            invoice: $invoice,
        );
    }

    public function forRepairOrder(RepairOrder $repairOrder): BalanceDueResult
    {
        return $this->projectForRepairOrder($repairOrder)->balance;
    }

    /**
     * @param  Collection<int, RepairOrder>  $repairOrders
     * @return array<int, BalanceDueResult>
     */
    public function mapForRepairOrders(Collection $repairOrders): array
    {
        if ($repairOrders->isEmpty()) {
            return [];
        }

        $repairOrderIds = $repairOrders->map(fn (RepairOrder $repairOrder): int|string => $repairOrder->getKey())->all();

        $invoices = EstimateDocument::query()
            ->whereIn('repair_order_id', $repairOrderIds)
            ->where('document_type', FinancialDocumentType::Invoice->value)
            ->where('status', '!=', InvoiceStatus::Voided->value)
            ->get()
            ->keyBy('repair_order_id');

        $entriesByRepairOrderId = RepairOrderLedgerEntry::query()
            ->whereIn('repair_order_id', $repairOrderIds)
            ->active()
            ->get()
            ->groupBy('repair_order_id');

        $balances = [];

        foreach ($repairOrders as $repairOrder) {
            $invoice = $invoices->get($repairOrder->id);
            $entries = $entriesByRepairOrderId->get($repairOrder->id, collect());

            $balances[$repairOrder->id] = $this->buildBalanceResult($invoice, $entries);
        }

        return $balances;
    }

    public function syncInvoiceStatus(EstimateDocument $invoice): EstimateDocument
    {
        $balance = $this->forRepairOrder($invoice->repairOrder()->firstOrFail());

        if ($invoice->status === InvoiceStatus::Voided->value) {
            return $invoice;
        }

        $invoice->forceFill(['status' => $balance->invoiceStatus->value])->save();

        return $invoice->refresh();
    }

    public function issuedInvoice(RepairOrder $repairOrder): ?EstimateDocument
    {
        return $this->queryIssuedInvoice($repairOrder);
    }

    private function queryIssuedInvoice(RepairOrder $repairOrder): ?EstimateDocument
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
    private function queryActiveEntries(RepairOrder $repairOrder): Collection
    {
        return RepairOrderLedgerEntry::query()
            ->where('repair_order_id', $repairOrder->id)
            ->active()
            ->get();
    }

    /**
     * @param  Collection<int, RepairOrderLedgerEntry>  $entries
     */
    private function buildBalanceResult(?EstimateDocument $invoice, Collection $entries): BalanceDueResult
    {
        if ($invoice === null) {
            return new BalanceDueResult(
                hasIssuedInvoice: false,
                invoiceTotalCents: 0,
                depositsAppliedCents: 0,
                paymentsAppliedCents: 0,
                refundsAppliedCents: 0,
                adjustmentsCents: 0,
                creditsAppliedCents: 0,
                writeOffsCents: 0,
                balanceDueCents: 0,
                unappliedDepositsCents: $this->sumEntries($entries, [LedgerEntryType::Deposit]),
                invoiceStatus: InvoiceStatus::Issued,
            );
        }

        $invoiceTotal = InvoiceSnapshotBuilder::invoiceTotalCents($invoice->snapshot_json ?? []);
        $deposits = $this->sumEntries($entries, [LedgerEntryType::Deposit]);
        $payments = $this->sumEntries($entries, [LedgerEntryType::Payment]);
        $refunds = $this->sumEntries($entries, [LedgerEntryType::Refund]);
        $adjustments = $this->sumEntries($entries, [LedgerEntryType::Adjustment]);
        $credits = $this->sumEntries($entries, [LedgerEntryType::StoreCreditApplication]);
        $writeOffs = $this->sumEntries($entries, [LedgerEntryType::WriteOff]);

        $balanceDue = max(
            0,
            $invoiceTotal - $deposits - $payments - $writeOffs - $credits + $adjustments + $refunds,
        );

        return new BalanceDueResult(
            hasIssuedInvoice: true,
            invoiceTotalCents: $invoiceTotal,
            depositsAppliedCents: $deposits,
            paymentsAppliedCents: $payments,
            refundsAppliedCents: $refunds,
            adjustmentsCents: $adjustments,
            creditsAppliedCents: $credits,
            writeOffsCents: $writeOffs,
            balanceDueCents: $balanceDue,
            unappliedDepositsCents: 0,
            invoiceStatus: $this->resolveInvoiceStatus($invoice, $balanceDue, $invoiceTotal),
        );
    }

    private function resolveInvoiceStatus(EstimateDocument $invoice, int $balanceDue, int $invoiceTotal): InvoiceStatus
    {
        if ($invoice->status === InvoiceStatus::Voided->value) {
            return InvoiceStatus::Voided;
        }

        if ($balanceDue === 0) {
            return InvoiceStatus::Paid;
        }

        if ($balanceDue > 0 && $balanceDue < $invoiceTotal) {
            return InvoiceStatus::PartiallyPaid;
        }

        return InvoiceStatus::Issued;
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
