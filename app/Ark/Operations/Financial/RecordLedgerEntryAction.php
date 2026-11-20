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
        PaymentMethod|string $method,
        ?User $actor = null,
        ?string $reference = null,
        ?Carbon $recordedAt = null,
    ): RepairOrderLedgerEntry {
        return $this->record($repairOrder, LedgerEntryType::Deposit, $amountCents, $method, $actor, $reference, recordedAt: $recordedAt);
    }

    public function recordPayment(
        RepairOrder $repairOrder,
        int $amountCents,
        PaymentMethod|string $method,
        ?User $actor = null,
        ?string $reference = null,
        ?Carbon $recordedAt = null,
        array $contractPayload = [],
        ?int $paymentReceiptId = null,
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
            return $this->recordOverpayment(
                $repairOrder,
                $amountCents,
                $method,
                $balance->balanceDueCents,
                $invoice->id,
                $actor,
                $reference,
                $recordedAt,
                $contractPayload,
                $paymentReceiptId,
            );
        }

        $entries = [
            $this->persistEntry(
                $repairOrder,
                $invoice->id,
                LedgerEntryType::Payment,
                $amountCents,
                $method,
                $actor,
                $reference,
                recordedAt: $recordedAt,
                paymentReceiptId: $paymentReceiptId,
            ),
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

    /**
     * Settles credit due on a revised invoice: a refund pays it back; store credit adds it to the
     * customer's store credit balance. Either way the invoice no longer shows it as owed.
     */
    public function resolveCreditDue(
        RepairOrder $repairOrder,
        int $amountCents,
        bool $asStoreCredit,
        ?User $actor = null,
        ?string $reference = null,
    ): RepairOrderLedgerEntry {
        $repairOrder->loadMissing('customer');
        $balance = $this->balanceDue->forRepairOrder($repairOrder);

        if ($amountCents <= 0 || $amountCents > $balance->overpaidCents) {
            throw new RuntimeException('Enter an amount up to the credit due of $'.number_format($balance->overpaidCents / 100, 2).'.');
        }

        if (! $asStoreCredit) {
            return $this->recordRefund($repairOrder, $amountCents, $actor, $reference);
        }

        return DB::transaction(function () use ($repairOrder, $amountCents, $actor, $reference): RepairOrderLedgerEntry {
            $entry = $this->persistEntry(
                $repairOrder,
                $this->balanceDue->issuedInvoice($repairOrder)?->id,
                LedgerEntryType::StoreCreditIssuance,
                $amountCents,
                RepairOrderLedgerEntry::CREDIT_DUE_STORE_CREDIT,
                $actor,
                $reference,
            );
            $this->increaseStoreCredit($repairOrder->customer, $amountCents);
            $this->invoicePdfRefresh->markDirtyForRepairOrder($repairOrder);
            $this->paymentPostureSync->sync($repairOrder->fresh());

            return $entry;
        });
    }

    /**
     * Unapplied remainder from a multi-invoice receipt becomes customer store credit.
     * Anchored to a repair order for ledger lineage; does not change that invoice's balance.
     */
    public function issueUnappliedStoreCredit(
        RepairOrder $repairOrder,
        int $amountCents,
        PaymentMethod|string $method,
        ?User $actor = null,
        ?string $reference = null,
        ?Carbon $recordedAt = null,
        ?int $paymentReceiptId = null,
    ): RepairOrderLedgerEntry {
        $repairOrder->loadMissing('customer');

        if ($amountCents <= 0) {
            throw new RuntimeException('Store credit amount must be greater than zero.');
        }

        return DB::transaction(function () use ($repairOrder, $amountCents, $method, $actor, $reference, $recordedAt, $paymentReceiptId): RepairOrderLedgerEntry {
            $entry = $this->persistEntry(
                $repairOrder,
                $this->balanceDue->issuedInvoice($repairOrder)?->id,
                LedgerEntryType::StoreCreditIssuance,
                $amountCents,
                $method,
                $actor,
                $reference,
                notes: 'Unapplied payment remainder',
                recordedAt: $recordedAt,
                paymentReceiptId: $paymentReceiptId,
            );
            $this->increaseStoreCredit($repairOrder->customer, $amountCents);

            return $entry;
        });
    }

    /**
     * Spends existing customer account credit against an invoice balance. No new tender.
     */
    public function applyStoreCredit(
        RepairOrder $repairOrder,
        int $amountCents,
        ?User $actor = null,
        ?string $reference = null,
        ?Carbon $recordedAt = null,
        ?int $paymentReceiptId = null,
    ): RepairOrderLedgerEntry {
        $repairOrder->loadMissing('customer');

        if ($amountCents <= 0) {
            throw new RuntimeException('Store credit application amount must be greater than zero.');
        }

        $invoice = $this->balanceDue->issuedInvoice($repairOrder);
        if ($invoice === null) {
            throw new RuntimeException('Issue the final invoice before applying account credit.');
        }

        $balance = $this->balanceDue->forRepairOrder($repairOrder);
        if ($amountCents > $balance->balanceDueCents) {
            throw new RuntimeException(
                'Cannot apply more credit than the $'.number_format($balance->balanceDueCents / 100, 2).' balance due.',
            );
        }

        return DB::transaction(function () use ($repairOrder, $amountCents, $actor, $reference, $recordedAt, $paymentReceiptId, $invoice): RepairOrderLedgerEntry {
            $customer = Customer::query()->whereKey($repairOrder->customer_id)->lockForUpdate()->firstOrFail();
            $available = (int) $customer->store_credit_balance_cents;
            if ($amountCents > $available) {
                throw new RuntimeException(
                    'Account credit available is $'.number_format($available / 100, 2).'.',
                );
            }

            $entry = $this->persistEntry(
                $repairOrder,
                $invoice->id,
                LedgerEntryType::StoreCreditApplication,
                $amountCents,
                PaymentMethod::StoreCredit,
                $actor,
                $reference,
                notes: 'Applied account credit',
                recordedAt: $recordedAt,
                paymentReceiptId: $paymentReceiptId,
            );
            $this->decreaseStoreCredit($customer, $amountCents);
            $this->invoicePdfRefresh->markDirtyForRepairOrder($repairOrder);
            $this->paymentPostureSync->sync($repairOrder->fresh());

            return $entry;
        });
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
        PaymentMethod|string $method,
        int $balanceDueCents,
        int $invoiceId,
        ?User $actor,
        ?string $reference,
        ?Carbon $recordedAt = null,
        array $contractPayload = [],
        ?int $paymentReceiptId = null,
    ): array {
        // Cash tender above balance is change at the counter - never store credit.
        if (PaymentMethod::isCash($method)) {
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
                    paymentReceiptId: $paymentReceiptId,
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
            $entries[] = $this->persistEntry(
                $repairOrder,
                $invoiceId,
                LedgerEntryType::Payment,
                $balanceDueCents,
                $method,
                $actor,
                $reference,
                recordedAt: $recordedAt,
                paymentReceiptId: $paymentReceiptId,
            );
        }

        $excess = $amountCents - $balanceDueCents;

        if ($excess > 0) {
            $entries[] = $this->persistEntry(
                $repairOrder,
                $invoiceId,
                LedgerEntryType::StoreCreditIssuance,
                $excess,
                $method,
                $actor,
                $reference,
                recordedAt: $recordedAt,
                paymentReceiptId: $paymentReceiptId,
            );
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
        PaymentMethod|string|null $method,
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
        PaymentMethod|string|null $method,
        ?User $actor,
        ?string $reference = null,
        ?string $notes = null,
        ?Carbon $recordedAt = null,
        ?int $paymentReceiptId = null,
    ): RepairOrderLedgerEntry {
        $repairOrder->loadMissing('customer');

        return DB::transaction(function () use ($repairOrder, $invoiceId, $type, $amountCents, $method, $actor, $reference, $notes, $recordedAt, $paymentReceiptId): RepairOrderLedgerEntry {
            $entry = RepairOrderLedgerEntry::query()->create([
                'repair_order_id' => $repairOrder->id,
                'customer_id' => $repairOrder->customer_id,
                'financial_document_id' => $invoiceId,
                'customer_payment_receipt_id' => $paymentReceiptId,
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

    private function decreaseStoreCredit(Customer $customer, int $amountCents): void
    {
        $next = (int) $customer->store_credit_balance_cents - $amountCents;
        if ($next < 0) {
            throw new RuntimeException('Insufficient account credit.');
        }

        $customer->forceFill([
            'store_credit_balance_cents' => $next,
        ])->save();
    }
}
