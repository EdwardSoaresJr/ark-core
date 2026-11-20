<?php

namespace App\Ark\Operations\Financial;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Documents\EstimateDocument;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Applies existing account credit to outstanding invoices without a new tender or card charge.
 */
final class ApplyCustomerStoreCreditAction
{
    public function __construct(
        private readonly BalanceDueCalculator $balanceDue,
        private readonly RecordLedgerEntryAction $ledger,
        private readonly EstimateTotalsCalculator $totals,
    ) {}

    /**
     * @param  list<array{invoice_id: int, amount_cents: int}>  $allocations
     */
    public function execute(
        Customer $customer,
        array $allocations,
        string $intentKey,
        ?User $actor = null,
        ?string $reference = null,
        ?Carbon $recordedAt = null,
    ): CustomerPaymentReceipt {
        $intentKey = trim($intentKey);
        if ($intentKey === '' || preg_match('/^[0-9a-fA-F-]{36}$/', $intentKey) !== 1) {
            throw ValidationException::withMessages([
                'intent_key' => 'A payment submission key is required.',
            ]);
        }

        $normalized = $this->normalizeAllocations($customer, $allocations);
        $amountCents = array_sum(array_column($normalized, 'amount_cents'));
        if ($amountCents <= 0) {
            throw ValidationException::withMessages([
                'allocations' => 'Select at least one invoice and credit amount.',
            ]);
        }

        $available = (int) $customer->fresh()->store_credit_balance_cents;
        if ($amountCents > $available) {
            throw ValidationException::withMessages([
                'amount' => 'Account credit available is $'.number_format($available / 100, 2)
                    .'. Cannot apply $'.number_format($amountCents / 100, 2).'.',
            ]);
        }

        $fingerprint = hash('sha256', json_encode([
            'amount_cents' => $amountCents,
            'allocations' => $normalized,
            'customer_id' => (int) $customer->id,
            'payment_method' => PaymentMethod::StoreCredit->value,
            'reference' => $reference,
        ], JSON_THROW_ON_ERROR));

        $existing = CustomerPaymentReceipt::query()->where('intent_key', $intentKey)->first();
        if ($existing !== null) {
            return $this->replayOrReject($existing, $customer, $fingerprint);
        }

        $recordedAt ??= now();

        try {
            return DB::transaction(function () use (
                $customer,
                $amountCents,
                $normalized,
                $intentKey,
                $fingerprint,
                $actor,
                $reference,
                $recordedAt,
            ): CustomerPaymentReceipt {
                $lockedCustomer = Customer::query()->whereKey($customer->id)->lockForUpdate()->firstOrFail();
                $available = (int) $lockedCustomer->store_credit_balance_cents;
                if ($amountCents > $available) {
                    throw ValidationException::withMessages([
                        'amount' => 'Account credit available is $'.number_format($available / 100, 2).'.',
                    ]);
                }

                $lockedReceipt = CustomerPaymentReceipt::query()
                    ->where('intent_key', $intentKey)
                    ->lockForUpdate()
                    ->first();
                if ($lockedReceipt !== null) {
                    return $this->replayOrReject($lockedReceipt, $lockedCustomer, $fingerprint);
                }

                $receipt = CustomerPaymentReceipt::query()->create([
                    'customer_id' => $lockedCustomer->id,
                    'intent_key' => $intentKey,
                    'payload_fingerprint' => $fingerprint,
                    'amount_cents' => $amountCents,
                    'allocated_cents' => 0,
                    'unapplied_cents' => 0,
                    'change_cents' => 0,
                    'refund_pending_cents' => 0,
                    'payment_method' => PaymentMethod::StoreCredit,
                    'reference' => $reference,
                    'notes' => 'Applied existing account credit; no new charge.',
                    'recorded_at' => $recordedAt,
                    'recorded_by' => $actor?->id,
                ]);

                // Lock invoices/ROs in stable id order after the customer lock to avoid
                // deadlocks and to re-check balances under concurrency.
                $ordered = $normalized;
                usort($ordered, fn (array $a, array $b): int => $a['invoice_id'] <=> $b['invoice_id']);

                $applied = 0;
                foreach ($ordered as $row) {
                    /** @var EstimateDocument $invoice */
                    $invoice = EstimateDocument::query()
                        ->whereKey($row['invoice_id'])
                        ->lockForUpdate()
                        ->firstOrFail();

                    if ($invoice->document_type !== FinancialDocumentType::Invoice) {
                        throw ValidationException::withMessages([
                            'allocations' => 'Only finalized invoices can receive account credit.',
                        ]);
                    }

                    /** @var RepairOrder $repairOrder */
                    $repairOrder = RepairOrder::query()
                        ->whereKey($invoice->repair_order_id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    if ((int) $repairOrder->customer_id !== (int) $lockedCustomer->id) {
                        throw ValidationException::withMessages([
                            'allocations' => 'All invoices must belong to this customer.',
                        ]);
                    }

                    // Serialize ledger readers/writers for this RO before balance math.
                    RepairOrderLedgerEntry::query()
                        ->where('repair_order_id', $repairOrder->id)
                        ->whereNull('voided_at')
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get(['id']);

                    $balance = $this->balanceDue->forRepairOrder($repairOrder->fresh());
                    if ($balance->balanceDueCents <= 0) {
                        throw ValidationException::withMessages([
                            'allocations' => 'Invoice #'.($invoice->document_number ?? $invoice->id)
                                .' has no outstanding balance.',
                        ]);
                    }

                    if ($row['amount_cents'] > $balance->balanceDueCents) {
                        throw ValidationException::withMessages([
                            'allocations' => 'Credit exceeds the outstanding balance on invoice #'
                                .($invoice->document_number ?? $invoice->id).'.',
                        ]);
                    }

                    $repairOrder->ensureOpenForSettlement();

                    $this->ledger->applyStoreCredit(
                        $repairOrder->fresh(),
                        $row['amount_cents'],
                        $actor,
                        $reference,
                        $recordedAt,
                        (int) $receipt->id,
                        customerAlreadyLocked: true,
                    );
                    $applied += $row['amount_cents'];
                }

                $receipt->forceFill([
                    'allocated_cents' => $applied,
                ])->save();

                if ($receipt->amount_cents !== $receipt->balancedAmountCents()) {
                    throw new RuntimeException('Store credit application receipt amounts do not balance.');
                }

                if ((int) $lockedCustomer->fresh()->store_credit_balance_cents !== ($available - $applied)) {
                    throw new RuntimeException('Store credit balance did not decrease by the applied amount.');
                }

                return $receipt->fresh(['ledgerEntries']);
            });
        } catch (UniqueConstraintViolationException) {
            $existing = CustomerPaymentReceipt::query()->where('intent_key', $intentKey)->firstOrFail();

            return $this->replayOrReject($existing, $customer, $fingerprint);
        }
    }

    /**
     * @param  list<array{invoice_id?: mixed, amount_cents?: mixed, amount?: mixed}>  $allocations
     * @return list<array{invoice_id: int, amount_cents: int}>
     */
    private function normalizeAllocations(Customer $customer, array $allocations): array
    {
        $rows = [];

        foreach ($allocations as $allocation) {
            if (! is_array($allocation)) {
                continue;
            }

            $invoiceId = (int) ($allocation['invoice_id'] ?? 0);
            $amountCents = isset($allocation['amount_cents'])
                ? (int) $allocation['amount_cents']
                : $this->totals->unitPriceCents((string) ($allocation['amount'] ?? '0'));

            if ($invoiceId <= 0 || $amountCents <= 0) {
                continue;
            }

            if (isset($rows[$invoiceId])) {
                throw ValidationException::withMessages([
                    'allocations' => 'Each invoice may only appear once in a credit application.',
                ]);
            }

            $invoice = EstimateDocument::query()->find($invoiceId);
            if ($invoice === null || $invoice->document_type !== FinancialDocumentType::Invoice) {
                throw ValidationException::withMessages([
                    'allocations' => 'Only finalized invoices can receive account credit.',
                ]);
            }

            $repairOrder = RepairOrder::query()->findOrFail($invoice->repair_order_id);
            if ((int) $repairOrder->customer_id !== (int) $customer->id) {
                throw ValidationException::withMessages([
                    'allocations' => 'All invoices must belong to this customer.',
                ]);
            }

            $rows[$invoiceId] = [
                'invoice_id' => $invoiceId,
                'amount_cents' => $amountCents,
            ];
        }

        return array_values($rows);
    }

    private function replayOrReject(
        CustomerPaymentReceipt $existing,
        Customer $customer,
        string $fingerprint,
    ): CustomerPaymentReceipt {
        if ((int) $existing->customer_id !== (int) $customer->id) {
            throw ValidationException::withMessages([
                'intent_key' => 'This submission does not belong to this customer.',
            ]);
        }

        if (! hash_equals((string) $existing->payload_fingerprint, $fingerprint)) {
            throw ValidationException::withMessages([
                'intent_key' => 'This submission was already recorded with different payment details.',
            ]);
        }

        return $existing;
    }
}
