<?php

namespace App\Ark\Operations\Financial;

use App\Ark\Operations\Documents\InvoicePdfRefresh;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class VoidLedgerEntryAction
{
    public function __construct(
        private readonly BalanceDueCalculator $balanceDue,
        private readonly RepairOrderPaymentPostureSync $paymentPostureSync,
        private readonly InvoicePdfRefresh $invoicePdfRefresh,
        private readonly NotifyRepairOrderFinancialChange $notifyFinancialChange,
    ) {}

    public function execute(RepairOrderLedgerEntry $entry, ?User $actor = null): RepairOrderLedgerEntry
    {
        if ($entry->isVoided()) {
            throw new RuntimeException('This ledger entry is already voided.');
        }

        return DB::transaction(function () use ($entry, $actor): RepairOrderLedgerEntry {
            if ($entry->entry_type === LedgerEntryType::StoreCreditIssuance) {
                $this->reverseStoreCredit($entry);
            }

            $entry->forceFill([
                'voided_at' => now(),
                'voided_by' => $actor?->id,
            ])->save();

            if ($entry->financial_document_id !== null) {
                $invoice = $entry->financialDocument()->first();
                if ($invoice !== null) {
                    $this->balanceDue->syncInvoiceStatus($invoice);
                }
            }

            $this->paymentPostureSync->sync($entry->repairOrder()->firstOrFail()->fresh());

            if ($entry->entry_type->affectsInvoiceBalance()) {
                $this->invoicePdfRefresh->markDirtyForRepairOrder($entry->repairOrder()->firstOrFail());
            }

            $repairOrder = $entry->repairOrder()->firstOrFail()->fresh();
            $this->notifyFinancialChange->notify($repairOrder, reason: 'ledger_voided', actor: $actor);

            return $entry->refresh();
        });
    }

    private function reverseStoreCredit(RepairOrderLedgerEntry $entry): void
    {
        $customer = $entry->customer()->firstOrFail();
        $nextBalance = (int) $customer->store_credit_balance_cents - (int) $entry->amount_cents;

        if ($nextBalance < 0) {
            throw new RuntimeException('Cannot void store credit issuance: customer credit balance is insufficient.');
        }

        $customer->forceFill(['store_credit_balance_cents' => $nextBalance])->save();
    }
}
