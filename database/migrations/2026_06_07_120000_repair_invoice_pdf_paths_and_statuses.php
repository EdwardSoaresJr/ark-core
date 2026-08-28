<?php

use App\Ark\Operations\Documents\DocumentPdfPath;
use App\Ark\Operations\Documents\EstimateDocument;
use App\Ark\Operations\Financial\BalanceDueCalculator;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $balanceDue = app(BalanceDueCalculator::class);

        EstimateDocument::query()
            ->where('document_type', 'invoice')
            ->whereIn('status', ['draft', 'generating', 'generated', 'failed'])
            ->each(function (EstimateDocument $document) use ($balanceDue): void {
                $balanceDue->syncInvoiceStatus($document);
            });

        EstimateDocument::query()
            ->where('document_type', 'invoice')
            ->get()
            ->each(function (EstimateDocument $document): void {
                if (! DocumentPdfPath::matches($document)) {
                    $document->forceFill(['needs_pdf_refresh' => true])->save();
                }
            });

        EstimateDocument::query()
            ->where('document_type', 'estimate')
            ->whereHas('repairOrder', fn ($query) => $query->where('status', RepairOrderStatus::Closed->value))
            ->update([
                'needs_pdf_refresh' => true,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        //
    }
};
