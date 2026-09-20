<?php

namespace App\Ark\Operations\Documents;

use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class EstimateDocumentPdfController
{
    public function show(RepairOrder $repairOrder, EstimateDocument $document, EstimateDocumentService $documents): Response
    {
        $document = $documents->ensureCurrent($document, request()->user());
        $this->ensureViewablePdf($repairOrder, $document, $documents);
        $document->markPresentedToCustomer();

        return DocumentPdfHttpResponse::inline(
            Storage::disk('local')->get($document->pdf_path),
            $this->filename($repairOrder, $document),
        );
    }

    public function download(RepairOrder $repairOrder, EstimateDocument $document, EstimateDocumentService $documents): Response
    {
        $document = $documents->ensureCurrent($document, request()->user());
        $this->ensureViewablePdf($repairOrder, $document, $documents);
        $document->markPresentedToCustomer();

        return DocumentPdfHttpResponse::attachment(
            Storage::disk('local')->get($document->pdf_path),
            $this->filename($repairOrder, $document),
        );
    }

    private function ensureViewablePdf(RepairOrder $repairOrder, EstimateDocument $document, EstimateDocumentService $documents): void
    {
        abort_unless($document->repair_order_id === $repairOrder->id, 404);
        abort_unless($documents->hasViewablePdf($document), 404);
    }

    private function filename(RepairOrder $repairOrder, EstimateDocument $document): string
    {
        if ($document->isInvoice()) {
            return sprintf('invoice-ro-%d.pdf', $repairOrder->repair_order_id);
        }

        return sprintf('estimate-ro-%d-current.pdf', $repairOrder->repair_order_id);
    }
}
