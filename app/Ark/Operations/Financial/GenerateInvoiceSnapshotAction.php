<?php

namespace App\Ark\Operations\Financial;

use App\Ark\Operations\Documents\EstimateDocument;
use App\Ark\Operations\Documents\EstimateDocumentService;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

final class GenerateInvoiceSnapshotAction
{
    public function __construct(
        private readonly InvoiceSnapshotBuilder $snapshotBuilder,
        private readonly EstimateTotalsCalculator $calculator,
        private readonly BalanceDueCalculator $balanceDue,
        private readonly EstimateDocumentService $documents,
        private readonly RepairOrderPaymentPostureSync $paymentPostureSync,
    ) {}

    public function execute(RepairOrder $repairOrder, ?User $actor = null): EstimateDocument
    {
        $repairOrder = $repairOrder->fresh();
        $repairOrder->ensureOpenForEditing();

        if (! $repairOrder->status->is(RepairOrderStatus::ReadyPickup)) {
            throw new RuntimeException('Generate the final invoice once the repair order is ready for pickup.');
        }

        if ($this->balanceDue->issuedInvoice($repairOrder) !== null) {
            throw new RuntimeException('A final invoice has already been issued for this repair order.');
        }

        if (! $this->calculator->hasApprovedInvoiceableWork($repairOrder)) {
            throw new RuntimeException('Approved work is required before generating a final invoice.');
        }

        return DB::transaction(function () use ($repairOrder, $actor): EstimateDocument {
            $issuedAt = now();

            $invoice = EstimateDocument::query()->create([
                'repair_order_id' => $repairOrder->id,
                'document_type' => FinancialDocumentType::Invoice->value,
                'document_number' => 1,
                'snapshot_json' => $this->snapshotBuilder->build($repairOrder, $actor),
                'status' => InvoiceStatus::Issued->value,
                'issued_at' => $issuedAt,
                'generated_at' => $issuedAt,
                'created_by' => $actor?->id,
                'needs_pdf_refresh' => true,
            ]);

            try {
                $this->documents->generatePdf($invoice);
            } catch (Throwable $exception) {
                Log::warning('Final invoice PDF could not be generated at issue time.', [
                    'invoice_document_id' => $invoice->id,
                    'repair_order_id' => $repairOrder->id,
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]);
            }

            $this->paymentPostureSync->sync($repairOrder->fresh());

            return $invoice->refresh();
        });
    }
}
