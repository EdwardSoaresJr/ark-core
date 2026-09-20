<?php

namespace App\Ark\Operations\Financial;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Documents\InvoicePdfRefresh;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class RecordLedgerEntryAction
{
    public function __construct(
        private readonly BalanceDueCalculator $balanceDue,
        private readonly RepairOrderPaymentPostureSync $paymentPostureSync,
        private readonly InvoicePdfRefresh $invoicePdfRefresh,
        private readonly EmitPaymentReceivedEvent $emitPaymentReceived,
    ) {}

    public function recordDeposit(
        RepairOrder $repairOrder,
        int $amountCents,
        PaymentMethod $method,
        ?User $actor = null,
        ?string $reference = null,
        ?Carbon $recordedAt = null,
    ): RepairOrderLedgerEntry {
        return $this->record($repairOrder, LedgerEntryType::Deposit, $amountCents, $method, $actor, $reference, recordedAt: $recordedAt);
    }

    public function recordPayment(
        RepairOrder $repairOrder,
        int $amountCents,
        PaymentMethod $method,
        ?User $actor = null,
        ?string $reference = null,
        ?Carbon $recordedAt = null,
        array $contractPayload = [],
    ): array {
        $repairOrder->loadMissing('customer');
        $invoice = $this->balanceDue->issuedInvoice($repairOrder);

        if ($invoice === null) {
            throw new RuntimeException('Issue the final invoice before recording payments against this repair order.');
        }

        if ($amountCents <= 0) {
            throw new RuntimeException('Payment amount must be greater than zero.');
        }

        $balance = $this->balanceDue->forRepairOrder($repairOrder);

        if ($amountCents > $balance->balanceDueCents) {
            return $this->recordOverpayment($repairOrder, $amountCents, $method, $balance->balanceDueCents, $invoice->id, $actor, $reference, $recordedAt, $contractPayload);
        }

        $entries = [
            $this->persistEntry($repairOrder, $invoice->id, LedgerEntryType::Payment, $amountCents, $method, $actor, $reference, recordedAt: $recordedAt),
        ];

        $this->paymentPostureSync->sync($repairOrder->fresh());

        $this->emitPaymentReceived->emit(
            $repairOrder->fresh(),
            $amountCents,
            $method,
            $actor,
            $reference,
            $entries[0]->id,
            $contractPayload,
        );

        return $entries;
    }

    public function recordRefund(
        RepairOrder $repairOrder,
        int $amountCents,
        ?User $actor = null,
        ?string $reference = null,
    ): RepairOrderLedgerEntry {
        $invoice = $this->balanceDue->issuedInvoice($repairOrder);

        if ($invoice === null) {
            throw new RuntimeException('Issue the final invoice before recording refunds.');
        }

        return $this->record($repairOrder, LedgerEntryType::Refund, $amountCents, null, $actor, $reference, $invoice->id);
    }

    public function recordAdjustment(
        RepairOrder $repairOrder,
        int $amountCents,
        ?User $actor = null,
        ?string $notes = null,
    ): RepairOrderLedgerEntry {
        $invoice = $this->balanceDue->issuedInvoice($repairOrder);

        if ($invoice === null) {
            throw new RuntimeException('Issue the final invoice before recording adjustments.');
        }

        return $this->record($repairOrder, LedgerEntryType::Adjustment, $amountCents, null, $actor, null, $invoice->id, $notes);
    }

    public function recordWriteOff(
        RepairOrder $repairOrder,
        int $amountCents,
        ?User $actor = null,
        ?string $notes = null,
        ?string $reference = null,
        ?Carbon $recordedAt = null,
    ): RepairOrderLedgerEntry {
        $invoice = $this->balanceDue->issuedInvoice($repairOrder);

        if ($invoice === null) {
            throw new RuntimeException('Issue the final invoice before recording write-offs.');
        }

        if ($amountCents <= 0) {
            throw new RuntimeException('Write-off amount must be greater than zero.');
        }

        $entry = $this->persistEntry(
            $repairOrder,
            $invoice->id,
            LedgerEntryType::WriteOff,
            $amountCents,
            null,
            $actor,
            $reference,
            $notes,
            $recordedAt,
        );

        $this->paymentPostureSync->sync($repairOrder->fresh());

        return $entry;
    }

    /**
     * @return list<RepairOrderLedgerEntry>
     */
    private function recordOverpayment(
        RepairOrder $repairOrder,
        int $amountCents,
        PaymentMethod $method,
        int $balanceDueCents,
        int $invoiceId,
        ?User $actor,
        ?string $reference,
        ?Carbon $recordedAt = null,
        array $contractPayload = [],
    ): array {
        // Cash tender above balance is change at the counter — never store credit.
        if ($method === PaymentMethod::Cash) {
            if ($balanceDueCents <= 0) {
                throw new RuntimeException('Nothing is due on this invoice.');
            }

            $entries = [
                $this->persistEntry(
                    $repairOrder,
                    $invoiceId,
                    LedgerEntryType::Payment,
                    $balanceDueCents,
                    $method,
                    $actor,
                    $reference,
                    recordedAt: $recordedAt,
                ),
            ];

            $this->paymentPostureSync->sync($repairOrder->fresh());

            $this->emitPaymentReceived->emit(
                $repairOrder->fresh(),
                $balanceDueCents,
                $method,
                $actor,
                $reference,
                $entries[0]->id,
                $contractPayload,
            );

            return $entries;
        }

        $entries = [];

        if ($balanceDueCents > 0) {
            $entries[] = $this->persistEntry($repairOrder, $invoiceId, LedgerEntryType::Payment, $balanceDueCents, $method, $actor, $reference, recordedAt: $recordedAt);
        }

        $excess = $amountCents - $balanceDueCents;

        if ($excess > 0) {
            $entries[] = $this->persistEntry($repairOrder, $invoiceId, LedgerEntryType::StoreCreditIssuance, $excess, $method, $actor, $reference, recordedAt: $recordedAt);
            $this->increaseStoreCredit($repairOrder->customer, $excess);
        }

        $this->paymentPostureSync->sync($repairOrder->fresh());

        $primaryPayment = collect($entries)->first(
            fn (RepairOrderLedgerEntry $entry): bool => $entry->entry_type === LedgerEntryType::Payment,
        );

        if ($primaryPayment instanceof RepairOrderLedgerEntry) {
            $this->emitPaymentReceived->emit(
                $repairOrder->fresh(),
                $amountCents,
                $method,
                $actor,
                $reference,
                $primaryPayment->id,
                $contractPayload,
            );
        }

        return $entries;
    }

    private function record(
        RepairOrder $repairOrder,
        LedgerEntryType $type,
        int $amountCents,
        ?PaymentMethod $method,
        ?User $actor,
        ?string $reference = null,
        ?int $invoiceId = null,
        ?string $notes = null,
        ?Carbon $recordedAt = null,
    ): RepairOrderLedgerEntry {
        if ($amountCents <= 0) {
            throw new RuntimeException('Ledger entry amount must be greater than zero.');
        }

        $invoiceId ??= $this->balanceDue->issuedInvoice($repairOrder)?->id;

        if ($type->affectsInvoiceBalance() && $invoiceId === null && $type !== LedgerEntryType::Deposit) {
            throw new RuntimeException('Issue the final invoice before recording '.$type->value.' entries.');
        }

        $entry = $this->persistEntry($repairOrder, $invoiceId, $type, $amountCents, $method, $actor, $reference, $notes, $recordedAt);
        $this->paymentPostureSync->sync($repairOrder->fresh());

        return $entry;
    }

    private function persistEntry(
        RepairOrder $repairOrder,
        ?int $invoiceId,
        LedgerEntryType $type,
        int $amountCents,
        ?PaymentMethod $method,
        ?User $actor,
        ?string $reference = null,
        ?string $notes = null,
        ?Carbon $recordedAt = null,
    ): RepairOrderLedgerEntry {
        $repairOrder->loadMissing('customer');

        return DB::transaction(function () use ($repairOrder, $invoiceId, $type, $amountCents, $method, $actor, $reference, $notes, $recordedAt): RepairOrderLedgerEntry {
            $entry = RepairOrderLedgerEntry::query()->create([
                'repair_order_id' => $repairOrder->id,
                'customer_id' => $repairOrder->customer_id,
                'financial_document_id' => $invoiceId,
                'entry_type' => $type,
                'payment_method' => $method,
                'amount_cents' => $amountCents,
                'reference' => $reference,
                'notes' => $notes,
                'recorded_at' => $recordedAt ?? now(),
                'recorded_by' => $actor?->id,
            ]);

            if ($invoiceId !== null) {
                $invoice = $entry->financialDocument()->first();
                if ($invoice !== null) {
                    $this->balanceDue->syncInvoiceStatus($invoice);
                }
            }

            if ($type->affectsInvoiceBalance() && $this->balanceDue->issuedInvoice($repairOrder) !== null) {
                $this->invoicePdfRefresh->markDirtyForRepairOrder($repairOrder);
            }

            return $entry;
        });
    }

    private function increaseStoreCredit(Customer $customer, int $amountCents): void
    {
        $customer->forceFill([
            'store_credit_balance_cents' => (int) $customer->store_credit_balance_cents + $amountCents,
        ])->save();
    }
}
