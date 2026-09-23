<?php

use App\Ark\Operations\Documents\EstimateDocument;
use App\Ark\Operations\Financial\FinancialDocumentType;
use App\Ark\Operations\Financial\InvoiceStatus;
use App\Ark\Operations\RepairOrders\RepairOrder;

function freezePostedInvoiceSnapshot(
    RepairOrder $repairOrder,
    int $preTaxCents,
    int $taxCents,
    bool $includePreTaxField = true,
): void {
    $totals = [
        'tax_cents' => $taxCents,
        'total_cents' => $preTaxCents + $taxCents,
        'standing_discount_cents' => 0,
        'fees_cents' => 0,
    ];

    if ($includePreTaxField) {
        $totals['subtotal_before_tax_cents'] = $preTaxCents;
    }

    EstimateDocument::query()->create([
        'repair_order_id' => $repairOrder->id,
        'document_number' => 1,
        'document_type' => FinancialDocumentType::Invoice,
        'status' => InvoiceStatus::Issued,
        'snapshot_json' => ['totals' => $totals],
        'issued_at' => $repairOrder->posted_at ?? now(),
        'generated_at' => $repairOrder->posted_at ?? now(),
    ]);
}
