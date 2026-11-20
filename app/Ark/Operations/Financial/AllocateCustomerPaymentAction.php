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
 * Posts one customer payment across one or more outstanding invoices atomically.
 *
 * Accounting invariant on every receipt:
 * amount_cents = allocated_cents + unapplied_cents + change_cents + refund_pending_cents
 *
 * Excess disposition is selectable (keep credit / return change / pending refund),
 * with a per-customer default when none is supplied. Refund does not mean funds
 * were returned — it records a pending-refund liability until confirmed.
 */
final class AllocateCustomerPaymentAction
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
        int $amountCents,
        PaymentMethod|string $method,
        array $allocations,
        string $intentKey,
        ?User $actor = null,
        ?string $reference = null,
        ?Carbon $recordedAt = null,
        ?OverpaymentDisposition $disposition = null,
    ): CustomerPaymentReceipt {
        $intentKey = trim($intentKey);
        if ($intentKey === '' || preg_match('/^[0-9a-fA-F-]{36}$/', $intentKey) !== 1) {
            throw ValidationException::withMessages([
                'intent_key' => 'A payment submission key is required.',
            ]);
        }

        if ($amountCents <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Payment amount must be greater than zero.',
            ]);
        }

        $normalized = $this->normalizeAllocations($allocations);
        if ($normalized === []) {
            throw ValidationException::withMessages([
                'allocations' => 'Select at least one invoice and allocation amount.',
            ]);
        }

        $allocatedTotal = array_sum(array_column($normalized, 'amount_cents'));
        if ($allocatedTotal > $amountCents) {
            throw ValidationException::withMessages([
                'amount' => 'Allocated amounts cannot exceed the payment amount.',
            ]);
        }

        $methodValue = PaymentMethod::stored($method);
        $disposition = $this->resolveDisposition($customer, $methodValue, $amountCents, $allocatedTotal, $disposition);
        $fingerprint = $this->fingerprint(
            $customer,
            $amountCents,
            $methodValue,
            $normalized,
            $reference,
            $disposition,
        );

        $existing = CustomerPaymentReceipt::query()->where('intent_key', $intentKey)->first();
        if ($existing !== null) {
            return $this->replayOrReject($existing, $customer, $fingerprint);
        }

        $recordedAt ??= now();

        try {
            return DB::transaction(function () use (
                $customer,
                $amountCents,
                $methodValue,
                $normalized,
                $intentKey,
                $fingerprint,
                $actor,
                $reference,
                $recordedAt,
                $disposition,
            ): CustomerPaymentReceipt {
                $locked = CustomerPaymentReceipt::query()
                    ->where('intent_key', $intentKey)
                    ->lockForUpdate()
                    ->first();

                if ($locked !== null) {
                    return $this->replayOrReject($locked, $customer, $fingerprint);
                }

                $receipt = CustomerPaymentReceipt::query()->create([
                    'customer_id' => $customer->id,
                    'intent_key' => $intentKey,
                    'payload_fingerprint' => $fingerprint,
                    'amount_cents' => $amountCents,
                    'allocated_cents' => 0,
                    'unapplied_cents' => 0,
                    'change_cents' => 0,
                    'refund_pending_cents' => 0,
                    'payment_method' => $methodValue,
                    'reference' => $reference,
                    'recorded_at' => $recordedAt,
                    'recorded_by' => $actor?->id,
                ]);

                $anchorRepairOrder = null;
                $applied = 0;

                foreach ($normalized as $row) {
                    /** @var EstimateDocument $invoice */
                    $invoice = EstimateDocument::query()
                        ->whereKey($row['invoice_id'])
                        ->lockForUpdate()
                        ->firstOrFail();

                    if ($invoice->document_type !== FinancialDocumentType::Invoice) {
                        throw ValidationException::withMessages([
                            'allocations' => 'Only finalized invoices can receive payment.',
                        ]);
                    }

                    /** @var RepairOrder $repairOrder */
                    $repairOrder = RepairOrder::query()
                        ->whereKey($invoice->repair_order_id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    if ((int) $repairOrder->customer_id !== (int) $customer->id) {
                        throw ValidationException::withMessages([
                            'allocations' => 'All invoices must belong to this customer.',
                        ]);
                    }

                    $repairOrder->ensureOpenForSettlement();

                    $balance = $this->balanceDue->forRepairOrder($repairOrder);
                    if (! $balance->hasIssuedInvoice || $balance->balanceDueCents <= 0) {
                        throw ValidationException::withMessages([
                            'allocations' => 'Invoice #'.($invoice->document_number ?? $invoice->id).' has no outstanding balance.',
                        ]);
                    }

                    if ($row['amount_cents'] > $balance->balanceDueCents) {
                        throw ValidationException::withMessages([
                            'allocations' => 'Allocation for invoice #'.($invoice->document_number ?? $invoice->id)
                                .' exceeds the outstanding balance of $'.number_format($balance->balanceDueCents / 100, 2).'.',
                        ]);
                    }

                    $this->ledger->recordPayment(
                        $repairOrder,
                        $row['amount_cents'],
                        $methodValue,
                        $actor,
                        $reference,
                        $recordedAt,
                        paymentReceiptId: (int) $receipt->id,
                    );

                    $anchorRepairOrder = $repairOrder;
                    $applied += $row['amount_cents'];
                }

                $remainder = $amountCents - $applied;
                $unapplied = 0;
                $change = 0;
                $refundPending = 0;

                if ($remainder > 0) {
                    if ($disposition === OverpaymentDisposition::KeepCredit) {
                        if ($anchorRepairOrder === null) {
                            throw new RuntimeException('Cannot post account credit without an allocated invoice.');
                        }

                        $this->ledger->issueUnappliedStoreCredit(
                            $anchorRepairOrder->fresh(),
                            $remainder,
                            $methodValue,
                            $actor,
                            $reference,
                            $recordedAt,
                            (int) $receipt->id,
                        );
                        $unapplied = $remainder;
                    } elseif ($disposition === OverpaymentDisposition::ReturnChange) {
                        if (! PaymentMethod::isCash($methodValue)) {
                            throw ValidationException::withMessages([
                                'disposition' => 'Return change is only available for cash tenders. Keep as credit or mark a pending refund instead.',
                            ]);
                        }
                        $change = $remainder;
                    } elseif ($disposition === OverpaymentDisposition::Refund) {
                        // Liability only — funds are not treated as returned until a confirmed refund posts.
                        $refundPending = $remainder;
                        $receipt->forceFill([
                            'notes' => trim(($receipt->notes ? $receipt->notes.' ' : '')
                                .'Excess held as pending refund/return; not confirmed returned and not account credit.'),
                        ]);
                    } else {
                        throw ValidationException::withMessages([
                            'disposition' => 'Choose how to handle the $'.number_format($remainder / 100, 2).' excess payment.',
                        ]);
                    }
                }

                $receipt->forceFill([
                    'allocated_cents' => $applied,
                    'unapplied_cents' => $unapplied,
                    'change_cents' => $change,
                    'refund_pending_cents' => $refundPending,
                ])->save();

                if ($receipt->amount_cents !== $receipt->balancedAmountCents()) {
                    throw new RuntimeException('Payment receipt amounts do not balance.');
                }

                return $receipt->fresh(['ledgerEntries']);
            });
        } catch (UniqueConstraintViolationException) {
            $existing = CustomerPaymentReceipt::query()->where('intent_key', $intentKey)->firstOrFail();

            return $this->replayOrReject($existing, $customer, $fingerprint);
        }
    }

    /**
     * @param  list<array{invoice_id: int, amount_cents: int}>  $allocations
     */
    public function fingerprint(
        Customer $customer,
        int $amountCents,
        PaymentMethod|string $method,
        array $allocations,
        ?string $reference,
        ?OverpaymentDisposition $disposition = null,
    ): string {
        $normalized = $allocations;
        usort($normalized, fn (array $a, array $b): int => $a['invoice_id'] <=> $b['invoice_id']);

        $payload = [
            'amount_cents' => $amountCents,
            'allocations' => $normalized,
            'customer_id' => (int) $customer->id,
            'disposition' => $disposition?->value,
            'payment_method' => PaymentMethod::stored($method),
            'reference' => $reference,
        ];

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    private function resolveDisposition(
        Customer $customer,
        string $methodValue,
        int $amountCents,
        int $allocatedTotal,
        ?OverpaymentDisposition $requested,
    ): OverpaymentDisposition {
        $remainder = $amountCents - $allocatedTotal;
        if ($remainder <= 0) {
            return OverpaymentDisposition::KeepCredit;
        }

        $default = $customer->overpayment_disposition_default instanceof OverpaymentDisposition
            ? $customer->overpayment_disposition_default
            : OverpaymentDisposition::tryFrom((string) $customer->overpayment_disposition_default)
                ?? OverpaymentDisposition::Ask;

        $disposition = $requested ?? ($default === OverpaymentDisposition::Ask ? null : $default);
        $allowed = OverpaymentDisposition::paymentChoices($methodValue);

        // Customer "return when possible" is cash-only. On noncash, force an explicit choice —
        // never silently map to refund or credit.
        if ($disposition !== null
            && $disposition !== OverpaymentDisposition::Ask
            && ! in_array($disposition, $allowed, true)) {
            throw ValidationException::withMessages([
                'disposition' => 'This account prefers returning excess when possible, but that is not available for this payment method. Choose Keep as account credit or Pending refund / return.',
            ]);
        }

        if ($disposition === null || $disposition === OverpaymentDisposition::Ask) {
            throw ValidationException::withMessages([
                'disposition' => 'This payment has a $'.number_format($remainder / 100, 2)
                    .' excess. Choose Keep as account credit'
                    .(PaymentMethod::isCash($methodValue) ? ', Return change' : ', or Pending refund / return').'.',
            ]);
        }

        return $disposition;
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

    /**
     * @param  list<array{invoice_id?: mixed, amount_cents?: mixed, amount?: mixed}>  $allocations
     * @return list<array{invoice_id: int, amount_cents: int}>
     */
    private function normalizeAllocations(array $allocations): array
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
                    'allocations' => 'Each invoice may only appear once in a payment allocation.',
                ]);
            }

            $rows[$invoiceId] = [
                'invoice_id' => $invoiceId,
                'amount_cents' => $amountCents,
            ];
        }

        return array_values($rows);
    }
}
