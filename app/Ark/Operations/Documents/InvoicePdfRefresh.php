<?php

namespace App\Ark\Operations\Documents;

use App\Ark\Operations\Financial\FinancialDocumentType;
use App\Ark\Operations\Financial\InvoiceStatus;
use App\Ark\Operations\RepairOrders\RepairOrder;

final class InvoicePdfRefresh
{
    public function markDirtyForRepairOrder(RepairOrder $repairOrder): void
    {
        EstimateDocument::query()
            ->where('repair_order_id', $repairOrder->id)
            ->where('document_type', FinancialDocumentType::Invoice->value)
            ->where('status', '!=', InvoiceStatus::Voided->value)
            ->update([
                'needs_pdf_refresh' => true,
                'updated_at' => now(),
            ]);
    }
}
