<?php

namespace App\Ark\Operations\Financial;

use App\Ark\Operations\Documents\InvoicePdfRefresh;
use App\Ark\Operations\Payments\Capture\PaymentCaptureAttempt;
use App\Ark\Operations\Payments\Capture\PaymentCaptureAttemptStatus;
use App\Ark\Operations\Payments\PaymentGatewayAttempt;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Voids an internal ledger row. Processor-settled card captures cannot be voided here —
 * that would show money as returned in ARK while the card remains charged. Use a verified
 * processor refund path instead.
 */
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

        $this->assertNotProcessorSettledCapture($entry);

        return DB::transaction(function () use ($entry, $actor): RepairOrderLedgerEntry {
            /** @var RepairOrderLedgerEntry $locked */
            $locked = RepairOrderLedgerEntry::query()->whereKey($entry->id)->lockForUpdate()->firstOrFail();

            if ($locked->isVoided()) {
                throw new RuntimeException('This ledger entry is already voided.');
            }

            $this->assertNotProcessorSettledCapture($locked);

            if ($locked->entry_type === LedgerEntryType::StoreCreditIssuance) {
                $this->reverseStoreCredit($locked);
            }

            $locked->forceFill([
                'voided_at' => now(),
                'voided_by' => $actor?->id,
            ])->save();

            if ($locked->financial_document_id !== null) {
                $invoice = $locked->financialDocument()->first();
                if ($invoice !== null) {
                    $this->balanceDue->syncInvoiceStatus($invoice);
                }
            }

            $this->paymentPostureSync->sync($locked->repairOrder()->firstOrFail()->fresh());

            if ($locked->entry_type->affectsInvoiceBalance()) {
                $this->invoicePdfRefresh->markDirtyForRepairOrder($locked->repairOrder()->firstOrFail());
            }

            $repairOrder = $locked->repairOrder()->firstOrFail()->fresh();
            $this->notifyFinancialChange->notify($repairOrder, reason: 'ledger_voided', actor: $actor);

            return $locked->refresh();
        });
    }

    /**
     * A ledger payment posted from a confirmed processor capture must not be voided as if
     * the card were refunded. Manual cash/check/external-card rows remain voidable.
     */
    private function assertNotProcessorSettledCapture(RepairOrderLedgerEntry $entry): void
    {
        if (! $entry->entry_type->affectsInvoiceBalance()) {
            return;
        }

        $capture = PaymentCaptureAttempt::query()
            ->where('ledger_entry_id', $entry->id)
            ->where('status', PaymentCaptureAttemptStatus::Succeeded)
            ->first();

        if ($capture !== null && $this->isSettledProviderPayment($capture->provider, $capture->provider_payment_id)) {
            throw new RuntimeException(
                'This payment was collected by the card processor. Voiding it in ARK would show the balance as unpaid while the customer\'s card remains charged. Record a processor-confirmed refund instead of voiding.',
            );
        }

        if (! Schema::hasTable('payment_gateway_attempts')) {
            return;
        }

        $legacy = PaymentGatewayAttempt::query()
            ->where('ledger_entry_id', $entry->id)
            ->whereNotNull('square_payment_id')
            ->where('square_payment_id', '!=', '')
            ->first();

        if ($legacy !== null && $this->isSettledProviderPayment('square', $legacy->square_payment_id)) {
            throw new RuntimeException(
                'This payment was collected by the card processor. Voiding it in ARK would show the balance as unpaid while the customer\'s card remains charged. Record a processor-confirmed refund instead of voiding.',
            );
        }
    }

    private function isSettledProviderPayment(mixed $provider, mixed $providerPaymentId): bool
    {
        $providerKey = strtolower(trim((string) $provider));
        $paymentId = trim((string) $providerPaymentId);

        if ($providerKey === '' || $providerKey === 'record' || $paymentId === '') {
            return false;
        }

        // Local recorder placeholders are not processor settlements.
        return preg_match('/^capture-\d+$/', $paymentId) !== 1;
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
