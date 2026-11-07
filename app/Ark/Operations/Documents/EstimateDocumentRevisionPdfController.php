<?php

namespace App\Ark\Operations\Documents;

use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

/**
 * Serves the PDF stored for an earlier invoice version exactly as it was. It is never rendered
 * again, so current pricing, templates and shop settings cannot change it.
 */
class EstimateDocumentRevisionPdfController
{
    public function show(RepairOrder $repairOrder, EstimateDocument $document, int $revision): Response
    {
        return DocumentPdfHttpResponse::inline(
            Storage::disk('local')->get($this->storedPath($repairOrder, $document, $revision)),
            $this->filename($repairOrder, $revision),
        );
    }

    public function download(RepairOrder $repairOrder, EstimateDocument $document, int $revision): Response
    {
        return DocumentPdfHttpResponse::attachment(
            Storage::disk('local')->get($this->storedPath($repairOrder, $document, $revision)),
            $this->filename($repairOrder, $revision),
        );
    }

    private function storedPath(RepairOrder $repairOrder, EstimateDocument $document, int $revision): string
    {
        abort_unless($document->repair_order_id === $repairOrder->id && $document->isInvoice(), 404);

        $entry = collect($document->priorRevisions())->firstWhere('revision', $revision);
        $path = $entry['pdf_path'] ?? null;

        abort_unless(
            filled($path) && $path === InvoiceRevisionPdf::pathFor($document, $revision) && Storage::disk('local')->exists($path),
            404,
            'No PDF was stored for this invoice revision.',
        );

        return $path;
    }

    private function filename(RepairOrder $repairOrder, int $revision): string
    {
        return sprintf('invoice-ro-%s-revision-%d.pdf', $repairOrder->repair_order_id, $revision);
    }
}
