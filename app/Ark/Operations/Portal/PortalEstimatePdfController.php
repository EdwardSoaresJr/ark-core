<?php

namespace App\Ark\Operations\Portal;

use App\Ark\Operations\Documents\DocumentPdfHttpResponse;
use App\Ark\Operations\Documents\EstimateDocumentService;
use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class PortalEstimatePdfController
{
    public function __construct(
        private readonly ResolveEstimateAccessTokenAction $resolve,
        private readonly EstimateDocumentService $documents,
    ) {}

    public function view(string $token): Response
    {
        $document = $this->documentForToken($token);

        return DocumentPdfHttpResponse::inline(
            Storage::disk('local')->get($document->pdf_path),
            $this->filename($document->repairOrder),
        );
    }

    public function download(string $token): Response
    {
        $document = $this->documentForToken($token);

        return DocumentPdfHttpResponse::attachment(
            Storage::disk('local')->get($document->pdf_path),
            $this->filename($document->repairOrder),
        );
    }

    private function documentForToken(string $token)
    {
        $accessToken = $this->resolve->execute($token, touchViewed: false);

        abort_unless($accessToken !== null, 404);

        $repairOrder = $accessToken->repairOrder()->firstOrFail();
        $document = $this->documents->resolveForRepairOrder($repairOrder);

        if (! $this->documents->hasViewablePdf($document)) {
            throw new NotFoundHttpException('Estimate PDF is not available yet.');
        }

        return $document;
    }

    private function filename(RepairOrder $repairOrder): string
    {
        return sprintf('estimate-ro-%d.pdf', $repairOrder->repair_order_id);
    }
}
