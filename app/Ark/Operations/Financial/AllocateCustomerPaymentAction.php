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
    ): CustomerPaymentReceipt {
        $intentKey = trim($intentKey);
        if ($intentKey === '' || preg_match('/^[0-9a-fA-F-]{36}$/', $intentKey) !== 1) {
            throw ValidationException::withMessages([
                'intent_key' => 'A payment submission key is required.',
            ]);
        }

        $existing = CustomerPaymentReceipt::query()->where('intent_key', $intentKey)->first();
        if ($existing !== null) {
            return $existing;
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

        $recordedAt ??= now();
        $methodValue = PaymentMethod::stored($method);

        try {
            return DB::transaction(function () use (
                $customer,
                $amountCents,
                $methodValue,
                $normalized,
                $intentKey,
                $actor,
                $reference,
                $recordedAt,
                $allocatedTotal,
            ): CustomerPaymentReceipt {
                $receipt = CustomerPaymentReceipt::query()->create([
                    'customer_id' => $customer->id,
                    'intent_key' => $intentKey,
                    'amount_cents' => $amountCents,
                    'allocated_cents' => 0,
                    'unapplied_cents' => 0,
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

                if ($remainder > 0 && ! PaymentMethod::isCash($methodValue)) {
                    if ($anchorRepairOrder === null) {
                        throw new RuntimeException('Cannot post unapplied funds without an allocated invoice.');
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
                }

                $receipt->forceFill([
                    'allocated_cents' => $applied,
                    'unapplied_cents' => $unapplied,
                ])->save();

                return $receipt->fresh(['ledgerEntries']);
            });
        } catch (UniqueConstraintViolationException) {
            return CustomerPaymentReceipt::query()->where('intent_key', $intentKey)->firstOrFail();
        }
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
