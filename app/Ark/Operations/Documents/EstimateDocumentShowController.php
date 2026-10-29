<?php

namespace App\Ark\Operations\Documents;

use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Contracts\View\View;

class EstimateDocumentShowController
{
    public function __invoke(
        RepairOrder $repairOrder,
        EstimateDocument $document,
        EstimateDocumentService $documents,
        EstimateDocumentPdfSnapshot $pdfSnapshot,
        DocumentPdfPresenter $pdfPresenter,
    ): View {
        abort_unless($document->repair_order_id === $repairOrder->id, 404);

        // The HTML view renders from the snapshot; an issued invoice's PDF is not touched by opening it.
        if (! $document->isInvoice()) {
            $document = $documents->ensureCurrent($document, request()->user());
        }

        return view('operations.documents.estimates.show', [
            'repairOrder' => $repairOrder,
            'document' => $document,
            'snapshot' => $pdfPresenter->prepare($pdfSnapshot->resolve($document, request()->user())),
        ]);
    }
}
