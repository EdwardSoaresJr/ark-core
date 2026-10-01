<?php

namespace App\Ark\Operations\Portal;

use App\Ark\Operations\Documents\DocumentPdfPresenter;
use App\Ark\Operations\Documents\EstimateDocument;
use App\Ark\Operations\Documents\EstimateDocumentPdfSnapshot;
use App\Ark\Operations\Documents\EstimateDocumentService;
use App\Ark\Operations\RepairOrders\RepairOrder;

final class PortalEstimateSnapshot
{
    public function __construct(
        private readonly EstimateDocumentService $documents,
        private readonly EstimateDocumentPdfSnapshot $pdfSnapshot,
        private readonly DocumentPdfPresenter $presenter,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forRepairOrder(RepairOrder $repairOrder): array
    {
        $document = $this->resolveLivingDocument($repairOrder);

        return $this->presenter->prepareForCustomer(
            $this->pdfSnapshot->resolve($document, null),
        );
    }

    private function resolveLivingDocument(RepairOrder $repairOrder): EstimateDocument
    {
        $repairOrder->loadMissing('concerns.lines', 'vehicle', 'customer');

        return $this->documents->resolveForRepairOrder($repairOrder, null);
    }
}
