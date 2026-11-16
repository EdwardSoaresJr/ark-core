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
     * Processor-linked payments must not be voided as if the card were refunded.
     * Confirmed captures, ambiguous/in-flight attempts, and missing processor references
     * are all refused. Manual cash/check/external-card rows without a processor attempt remain voidable.
     */
    private function assertNotProcessorSettledCapture(RepairOrderLedgerEntry $entry): void
    {
        if (! $entry->entry_type->affectsInvoiceBalance()) {
            return;
        }

        $captures = PaymentCaptureAttempt::query()
            ->where('ledger_entry_id', $entry->id)
            ->get();

        foreach ($captures as $capture) {
            if ($this->mustNotVoidProcessorAttempt($capture->status, $capture->provider, $capture->provider_payment_id)) {
                throw new RuntimeException($this->processorVoidBlockedMessage($capture->status));
            }
        }

        if (! Schema::hasTable('payment_gateway_attempts')) {
            return;
        }

        $legacy = PaymentGatewayAttempt::query()
            ->where('ledger_entry_id', $entry->id)
            ->first();

        if ($legacy === null) {
            return;
        }

        $legacyPaymentId = trim((string) ($legacy->square_payment_id ?? ''));
        if ($legacyPaymentId !== '') {
            throw new RuntimeException($this->processorVoidBlockedMessage(PaymentCaptureAttemptStatus::Succeeded));
        }

        // A legacy gateway row without a Square id is still an uncertain processor link.
        throw new RuntimeException($this->processorVoidBlockedMessage(PaymentCaptureAttemptStatus::ReconciliationRequired));
    }

    private function mustNotVoidProcessorAttempt(
        PaymentCaptureAttemptStatus $status,
        mixed $provider,
        mixed $providerPaymentId,
    ): bool {
        $providerKey = strtolower(trim((string) $provider));
        if ($providerKey === '' || $providerKey === 'record') {
            return false;
        }

        $paymentId = trim((string) $providerPaymentId);
        $isPlaceholderId = $paymentId !== '' && preg_match('/^capture-\d+$/', $paymentId) === 1;

        // In-flight or reconciliation-required: never treat as a safe manual void.
        if (in_array($status, [
            PaymentCaptureAttemptStatus::Accepted,
            PaymentCaptureAttemptStatus::Pending,
            PaymentCaptureAttemptStatus::ReconciliationRequired,
        ], true)) {
            return true;
        }

        if ($status === PaymentCaptureAttemptStatus::Succeeded) {
            // Local recorder placeholders remain voidable; missing or real processor ids are not.
            return ! $isPlaceholderId;
        }

        // Failed/cancelled with a real provider payment id may still represent money movement.
        if (in_array($status, [
            PaymentCaptureAttemptStatus::Failed,
            PaymentCaptureAttemptStatus::Cancelled,
        ], true)) {
            return $paymentId !== '' && ! $isPlaceholderId;
        }

        return true;
    }

    private function processorVoidBlockedMessage(PaymentCaptureAttemptStatus $status): string
    {
        if (in_array($status, [
            PaymentCaptureAttemptStatus::Accepted,
            PaymentCaptureAttemptStatus::Pending,
            PaymentCaptureAttemptStatus::ReconciliationRequired,
        ], true) || $status === PaymentCaptureAttemptStatus::Succeeded) {
            return 'This payment was collected by the card processor. Voiding it in ARK would show the balance as unpaid while the customer\'s card remains charged. Record a processor-confirmed refund instead of voiding.';
        }

        return 'This payment is linked to a card processor attempt. Voiding it in ARK could misstate whether the customer was charged. Record a processor-confirmed refund or wait until the processor outcome is certain.';
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
