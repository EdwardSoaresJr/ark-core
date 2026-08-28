<?php

namespace App\Ark\Import;

use App\Ark\Operations\Documents\EstimateDocument;
use App\Ark\Operations\Financial\BalanceDueCalculator;
use App\Ark\Operations\Financial\FinancialDocumentType;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Financial\InvoiceSnapshotBuilder;
use App\Ark\Operations\Financial\InvoiceStatus;
use App\Ark\Operations\Financial\PaymentMethod;
use App\Ark\Operations\Financial\RecordLedgerEntryAction;
use App\Ark\Operations\Financial\RepairOrderLedgerEntry;
use App\Ark\Operations\Financial\RepairOrderPaymentPostureSync;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class LegacyInvoicePaymentBackfill
{
    public function __construct(
        private readonly LegacyArkSmsValueMapper $mapper,
        private readonly RecordLedgerEntryAction $ledger,
        private readonly BalanceDueCalculator $balanceDue,
        private readonly RepairOrderPaymentPostureSync $paymentPosture,
        private readonly InvoiceSnapshotBuilder $invoiceSnapshotBuilder,
        private readonly EstimateTotalsCalculator $totalsCalculator,
    ) {}

    /**
     * @return array{
     *     invoice_document_id: int|null,
     *     payments_recorded: int,
     *     payments_skipped: int,
     *     write_off_cents: int,
     *     balance_due_cents: int,
     *     paid: bool,
     * }
     */
    public function backfillRepairOrder(
        RepairOrder $repairOrder,
        string $legacyConnection,
        bool $dryRun = true,
        bool $writeOffRemainder = true,
        ?User $actor = null,
    ): array {
        $legacyRepairOrderId = (int) $repairOrder->repair_order_id;

        if ($legacyRepairOrderId <= 0) {
            throw new RuntimeException("Repair order #{$repairOrder->id} is missing a shop repair order id.");
        }

        $legacyInvoice = $this->legacyInvoiceForRepairOrder($legacyConnection, $legacyRepairOrderId);

        if ($legacyInvoice !== null) {
            return $this->backfillLegacyInvoicePayments(
                $repairOrder,
                $legacyInvoice,
                $this->legacyPaymentsForInvoice($legacyConnection, (int) $legacyInvoice->id),
                $dryRun,
                $writeOffRemainder,
                $actor,
            );
        }

        $legacyDeposits = $this->legacyDepositsForRepairOrder($legacyConnection, $legacyRepairOrderId);

        if ($legacyDeposits->isEmpty()) {
            throw new RuntimeException("Legacy invoice or deposit payments not found for shop RO #{$legacyRepairOrderId}.");
        }

        return $this->backfillLegacyDeposits(
            $repairOrder,
            $legacyDeposits,
            $dryRun,
            $writeOffRemainder,
            $actor,
        );
    }

    /**
     * @param  Collection<int, object>  $legacyPayments
     * @return array{
     *     invoice_document_id: int|null,
     *     payments_recorded: int,
     *     payments_skipped: int,
     *     write_off_cents: int,
     *     balance_due_cents: int,
     *     paid: bool,
     * }
     */
    private function backfillLegacyInvoicePayments(
        RepairOrder $repairOrder,
        object $legacyInvoice,
        Collection $legacyPayments,
        bool $dryRun,
        bool $writeOffRemainder,
        ?User $actor,
    ): array {
        if ($legacyPayments->isEmpty()) {
            throw new RuntimeException("Legacy invoice payments not found for shop RO #{$repairOrder->repair_order_id}.");
        }

        if ($dryRun) {
            $invoiceTotalCents = $this->mapper->dollarsToCents((string) $legacyInvoice->total);
            $paymentsTotalCents = $legacyPayments->sum(
                fn (object $payment): int => $this->mapper->dollarsToCents((string) $payment->amount),
            );
            $remainderCents = max(0, $invoiceTotalCents - $paymentsTotalCents);

            return [
                'invoice_document_id' => null,
                'payments_recorded' => $legacyPayments->count(),
                'payments_skipped' => 0,
                'write_off_cents' => $writeOffRemainder ? $remainderCents : 0,
                'balance_due_cents' => $writeOffRemainder ? 0 : $remainderCents,
                'paid' => $writeOffRemainder && $remainderCents >= 0,
            ];
        }

        return DB::transaction(function () use (
            $repairOrder,
            $legacyInvoice,
            $legacyPayments,
            $writeOffRemainder,
            $actor,
        ): array {
            $invoice = $this->ensureIssuedInvoice($repairOrder, $legacyInvoice);

            $paymentsRecorded = 0;
            $paymentsSkipped = 0;

            foreach ($legacyPayments as $legacyPayment) {
                $reference = $this->legacyPaymentReference((int) $legacyPayment->id);

                if ($this->ledgerEntryExists($repairOrder, $reference)) {
                    $paymentsSkipped++;

                    continue;
                }

                $amountCents = $this->mapper->dollarsToCents((string) $legacyPayment->amount);
                $paidAt = $this->parseTimestamp($legacyPayment->paid_at ?? $legacyPayment->applied_at ?? null);
                $method = $this->mapPaymentMethod((string) ($legacyPayment->method ?? 'card'));
                $noteReference = trim((string) ($legacyPayment->reference ?? ''));

                $this->ledger->recordPayment(
                    $repairOrder->fresh(),
                    $amountCents,
                    $method,
                    $actor,
                    $reference,
                    $paidAt,
                );

                if ($noteReference !== '' && $noteReference !== $reference) {
                    RepairOrderLedgerEntry::query()
                        ->where('repair_order_id', $repairOrder->id)
                        ->where('reference', $reference)
                        ->latest('id')
                        ->first()
                        ?->forceFill(['notes' => 'Legacy v1 reference: '.$noteReference])
                        ->save();
                }

                $paymentsRecorded++;
            }

            $writeOffCents = 0;

            if ($writeOffRemainder) {
                $balance = $this->balanceDue->forRepairOrder($repairOrder->fresh());

                if ($balance->balanceDueCents > 0) {
                    $writeOffReference = 'legacy-v1-writeoff-invoice-'.(int) $legacyInvoice->id;
                    $writeOffCents = $balance->balanceDueCents;

                    if (! $this->ledgerEntryExists($repairOrder, $writeOffReference)) {
                        $this->ledger->recordWriteOff(
                            $repairOrder->fresh(),
                            $writeOffCents,
                            $actor,
                            'Legacy v1 import remainder — invoice rounding discrepancy, not customer payment.',
                            $writeOffReference,
                            $this->parseTimestamp($legacyInvoice->finalized_at ?? null),
                        );
                    }
                }
            }

            $repairOrder = $this->paymentPosture->sync($repairOrder->fresh());
            $balance = $this->balanceDue->forRepairOrder($repairOrder);

            return [
                'invoice_document_id' => $invoice->id,
                'payments_recorded' => $paymentsRecorded,
                'payments_skipped' => $paymentsSkipped,
                'write_off_cents' => $writeOffCents,
                'balance_due_cents' => $balance->balanceDueCents,
                'paid' => $balance->isPaid(),
            ];
        });
    }

    /**
     * @param  Collection<int, object>  $legacyDeposits
     * @return array{
     *     invoice_document_id: int|null,
     *     payments_recorded: int,
     *     payments_skipped: int,
     *     write_off_cents: int,
     *     balance_due_cents: int,
     *     paid: bool,
     * }
     */
    private function backfillLegacyDeposits(
        RepairOrder $repairOrder,
        Collection $legacyDeposits,
        bool $dryRun,
        bool $writeOffRemainder,
        ?User $actor,
    ): array {
        $repairOrder->loadMissing(['concerns.lines', 'lines']);
        $invoiceTotalCents = $this->totalsCalculator->totalsForApprovedWork($repairOrder)->totalCents();
        $depositsTotalCents = $legacyDeposits->sum(
            fn (object $deposit): int => (int) $deposit->amount,
        );

        if ($dryRun) {
            $remainderCents = max(0, $invoiceTotalCents - $depositsTotalCents);

            return [
                'invoice_document_id' => null,
                'payments_recorded' => $legacyDeposits->count(),
                'payments_skipped' => 0,
                'write_off_cents' => $writeOffRemainder ? $remainderCents : 0,
                'balance_due_cents' => $writeOffRemainder ? 0 : $remainderCents,
                'paid' => $writeOffRemainder && $remainderCents >= 0,
            ];
        }

        return DB::transaction(function () use (
            $repairOrder,
            $legacyDeposits,
            $writeOffRemainder,
            $actor,
        ): array {
            $invoice = $this->ensureIssuedInvoiceFromApprovedWork($repairOrder);

            $paymentsRecorded = 0;
            $paymentsSkipped = 0;

            foreach ($legacyDeposits as $legacyDeposit) {
                $reference = $this->legacyDepositReference((int) $legacyDeposit->id);

                if ($this->ledgerEntryExists($repairOrder, $reference)) {
                    $paymentsSkipped++;

                    continue;
                }

                $amountCents = (int) $legacyDeposit->amount;
                $paidAt = $this->parseTimestamp($legacyDeposit->received_at ?? null);
                $method = $this->mapPaymentMethod((string) ($legacyDeposit->method ?? 'card'));
                $noteReference = trim((string) ($legacyDeposit->provider_ref ?? $legacyDeposit->note ?? ''));

                $this->ledger->recordDeposit(
                    $repairOrder->fresh(),
                    $amountCents,
                    $method,
                    $actor,
                    $reference,
                    $paidAt,
                );

                if ($noteReference !== '') {
                    RepairOrderLedgerEntry::query()
                        ->where('repair_order_id', $repairOrder->id)
                        ->where('reference', $reference)
                        ->latest('id')
                        ->first()
                        ?->forceFill(['notes' => 'Legacy v1 reference: '.$noteReference])
                        ->save();
                }

                $paymentsRecorded++;
            }

            $writeOffCents = 0;

            if ($writeOffRemainder) {
                $balance = $this->balanceDue->forRepairOrder($repairOrder->fresh());

                if ($balance->balanceDueCents > 0) {
                    $writeOffReference = 'legacy-v1-writeoff-deposits-ro-'.$repairOrder->repair_order_id;
                    $writeOffCents = $balance->balanceDueCents;

                    if (! $this->ledgerEntryExists($repairOrder, $writeOffReference)) {
                            $latestPaidAt = $legacyDeposits
                                ->map(fn (object $deposit) => $this->parseTimestamp($deposit->received_at ?? null))
                                ->filter()
                                ->sort()
                                ->last() ?? now();

                        $this->ledger->recordWriteOff(
                            $repairOrder->fresh(),
                            $writeOffCents,
                            $actor,
                            'Legacy v1 import remainder — deposit total vs approved invoice, not customer payment.',
                            $writeOffReference,
                            $latestPaidAt,
                        );
                    }
                }
            }

            $repairOrder = $this->paymentPosture->sync($repairOrder->fresh());
            $balance = $this->balanceDue->forRepairOrder($repairOrder);

            return [
                'invoice_document_id' => $invoice->id,
                'payments_recorded' => $paymentsRecorded,
                'payments_skipped' => $paymentsSkipped,
                'write_off_cents' => $writeOffCents,
                'balance_due_cents' => $balance->balanceDueCents,
                'paid' => $balance->isPaid(),
            ];
        });
    }

    private function legacyInvoiceForRepairOrder(string $connection, int $legacyShopRepairOrderId): ?object
    {
        return DB::connection($connection)
            ->table('invoices')
            ->join('repair_orders', 'repair_orders.id', '=', 'invoices.repair_order_id')
            ->where('repair_orders.repair_order_id', $legacyShopRepairOrderId)
            ->orderByDesc('invoices.id')
            ->select('invoices.*')
            ->first();
    }

    /**
     * @return Collection<int, object>
     */
    private function legacyPaymentsForInvoice(string $connection, int $legacyInvoiceId): Collection
    {
        return DB::connection($connection)
            ->table('invoice_payments')
            ->where('invoice_id', $legacyInvoiceId)
            ->where('is_refund', false)
            ->orderBy('paid_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, object>
     */
    private function legacyDepositsForRepairOrder(string $connection, int $legacyShopRepairOrderId): Collection
    {
        if (! $this->legacyTableExists($connection, 'payments')) {
            return collect();
        }

        return DB::connection($connection)
            ->table('payments')
            ->join('repair_orders', 'repair_orders.id', '=', 'payments.repair_order_id')
            ->where('repair_orders.repair_order_id', $legacyShopRepairOrderId)
            ->where('payments.kind', 'deposit')
            ->where('payments.is_refund', false)
            ->orderBy('payments.received_at')
            ->orderBy('payments.id')
            ->select('payments.*')
            ->get();
    }

    private function legacyTableExists(string $connection, string $table): bool
    {
        return DB::connection($connection)->getSchemaBuilder()->hasTable($table);
    }

    private function ensureIssuedInvoiceFromApprovedWork(RepairOrder $repairOrder): EstimateDocument
    {
        $existingIssued = EstimateDocument::query()
            ->where('repair_order_id', $repairOrder->id)
            ->where('document_type', FinancialDocumentType::Invoice->value)
            ->where('status', '!=', InvoiceStatus::Voided->value)
            ->first();

        if ($existingIssued !== null) {
            return $existingIssued;
        }

        $repairOrder->loadMissing(['customer', 'vehicle', 'assignedTechnician', 'encounter.creator', 'concerns.lines', 'lines', 'estimateDocuments.creator']);
        $snapshot = $this->invoiceSnapshotBuilder->build($repairOrder, null, skipRecalculate: true);
        $issuedAt = isset($snapshot['generated_at'])
            ? Carbon::parse((string) $snapshot['generated_at'])
            : now();
        $documentNumber = (int) $repairOrder->repair_order_id;

        return EstimateDocument::query()->create([
            'repair_order_id' => $repairOrder->id,
            'document_type' => FinancialDocumentType::Invoice->value,
            'document_number' => $documentNumber,
            'snapshot_json' => $snapshot,
            'status' => InvoiceStatus::Issued->value,
            'issued_at' => $issuedAt,
            'generated_at' => $issuedAt,
            'needs_pdf_refresh' => true,
        ]);
    }

    private function ensureIssuedInvoice(RepairOrder $repairOrder, object $legacyInvoice): EstimateDocument
    {
        $existingIssued = EstimateDocument::query()
            ->where('repair_order_id', $repairOrder->id)
            ->where('document_type', FinancialDocumentType::Invoice->value)
            ->where('status', '!=', InvoiceStatus::Voided->value)
            ->first();

        if ($existingIssued !== null) {
            return $existingIssued;
        }

        $legacyInvoiceId = (int) $legacyInvoice->id;
        $issuedAt = $this->parseTimestamp($legacyInvoice->finalized_at ?? $legacyInvoice->created_at ?? null) ?? now();

        $document = EstimateDocument::query()
            ->where('repair_order_id', $repairOrder->id)
            ->where('legacy_arksms_invoice_id', $legacyInvoiceId)
            ->first()
            ?? EstimateDocument::query()
                ->where('repair_order_id', $repairOrder->id)
                ->orderByDesc('id')
                ->first();

        $snapshot = is_array($document?->snapshot_json) ? $document->snapshot_json : [];
        $snapshot['schema_version'] = 'legacy_import';
        $snapshot['document_type'] = FinancialDocumentType::Invoice->value;
        $snapshot['legacy_invoice_id'] = $legacyInvoiceId;
        $snapshot['totals'] = [
            'subtotal_cents' => $this->mapper->dollarsToCents((string) ($legacyInvoice->subtotal ?? 0)),
            'tax_cents' => $this->mapper->dollarsToCents((string) ($legacyInvoice->tax_total ?? $legacyInvoice->tax ?? 0)),
            'shop_fee_cents' => $this->mapper->dollarsToCents((string) ($legacyInvoice->shop_fees_total ?? $legacyInvoice->shop_fee ?? 0)),
            'total_cents' => $this->mapper->dollarsToCents((string) ($legacyInvoice->total ?? 0)),
        ];

        $documentNumber = (int) ($legacyInvoice->invoice_number ?? $legacyInvoiceId);
        if ($documentNumber <= 0) {
            $documentNumber = $legacyInvoiceId;
        }

        if ($document === null) {
            return EstimateDocument::query()->create([
                'legacy_arksms_invoice_id' => $legacyInvoiceId,
                'repair_order_id' => $repairOrder->id,
                'document_type' => FinancialDocumentType::Invoice->value,
                'document_number' => $documentNumber,
                'snapshot_json' => $snapshot,
                'status' => InvoiceStatus::Issued->value,
                'issued_at' => $issuedAt,
                'generated_at' => $issuedAt,
                'needs_pdf_refresh' => true,
            ]);
        }

        EstimateDocument::withoutEvents(function () use ($document, $legacyInvoiceId, $documentNumber, $snapshot, $issuedAt): void {
            $document->forceFill([
                'legacy_arksms_invoice_id' => $legacyInvoiceId,
                'document_type' => FinancialDocumentType::Invoice->value,
                'document_number' => $documentNumber,
                'snapshot_json' => $snapshot,
                'status' => InvoiceStatus::Issued->value,
                'issued_at' => $issuedAt,
                'generated_at' => $document->generated_at ?? $issuedAt,
                'needs_pdf_refresh' => true,
            ])->save();
        });

        return $document->refresh();
    }

    private function ledgerEntryExists(RepairOrder $repairOrder, string $reference): bool
    {
        return RepairOrderLedgerEntry::query()
            ->where('repair_order_id', $repairOrder->id)
            ->where('reference', $reference)
            ->active()
            ->exists();
    }

    private function legacyPaymentReference(int $legacyPaymentId): string
    {
        return 'legacy-v1-invoice-payment-'.$legacyPaymentId;
    }

    private function legacyDepositReference(int $legacyDepositId): string
    {
        return 'legacy-v1-deposit-'.$legacyDepositId;
    }

    private function mapPaymentMethod(string $method): PaymentMethod
    {
        return match (strtolower(trim($method))) {
            'cash' => PaymentMethod::Cash,
            'check', 'cheque' => PaymentMethod::Check,
            'financing', 'finance' => PaymentMethod::Financing,
            default => PaymentMethod::Card,
        };
    }

    private function parseTimestamp(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse((string) $value);
    }
}

