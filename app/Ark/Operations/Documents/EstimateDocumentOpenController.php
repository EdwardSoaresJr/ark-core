<?php

namespace App\Ark\Operations\Documents;

use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Throwable;

class EstimateDocumentOpenController
{
    public function show(RepairOrder $repairOrder): RedirectResponse
    {
        return redirect()->route('operations.repair-orders.estimate.pdf', $repairOrder);
    }

    public function pdf(RepairOrder $repairOrder, EstimateDocumentService $documents): Response|RedirectResponse
    {
        $document = $this->resolve($repairOrder, $documents);

        if (! $documents->hasViewablePdf($document)) {
            return $this->pdfFailureRedirect($repairOrder);
        }

        return DocumentPdfHttpResponse::inline(
            Storage::disk('local')->get($document->pdf_path),
            $this->filename($repairOrder),
        );
    }

    public function download(RepairOrder $repairOrder, EstimateDocumentService $documents): Response|RedirectResponse
    {
        $document = $this->resolve($repairOrder, $documents);

        if (! $documents->hasViewablePdf($document)) {
            return $this->pdfFailureRedirect($repairOrder);
        }

        return DocumentPdfHttpResponse::attachment(
            Storage::disk('local')->get($document->pdf_path),
            $this->filename($repairOrder),
        );
    }

    private function resolve(RepairOrder $repairOrder, EstimateDocumentService $documents): EstimateDocument
    {
        try {
            return $documents->resolveForRepairOrder($repairOrder, request()->user());
        } catch (Throwable) {
            abort(503, 'Estimate document could not be prepared.');
        }
    }

    private function filename(RepairOrder $repairOrder): string
    {
        return sprintf('estimate-ro-%d-current.pdf', $repairOrder->repair_order_id);
    }

    private function pdfFailureRedirect(RepairOrder $repairOrder): RedirectResponse
    {
        return redirect()
            ->back(fallback: route('operations.repair-orders.show', $repairOrder))
            ->with('error', 'Estimate PDF could not be generated. Wait a moment and try View PDF again.');
    }
}
