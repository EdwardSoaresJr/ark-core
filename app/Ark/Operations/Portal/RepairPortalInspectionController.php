<?php

namespace App\Ark\Operations\Portal;

use App\Ark\Operations\Documents\HtmlPdfBuilder;
use App\Ark\Operations\Inspections\InspectionCustomerEvidenceAllowlist;
use App\Ark\Operations\Inspections\InspectionFindingCardProjection;
use App\Ark\Operations\Inspections\InspectionItemPhoto;
use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Inspection report through the durable Repair Portal doorway (/r/{code}/inspection).
 */
final class RepairPortalInspectionController
{
    public function __construct(
        private readonly PortalInspectionPage $page,
        private readonly HtmlPdfBuilder $pdf,
    ) {}

    public function show(Request $request, string $code, ResolveRepairOrderPortalAccessAction $resolve): View
    {
        $repairOrder = $this->repairOrderWithFindings($code, $resolve);

        return $this->page->renderRepairPortal(
            $repairOrder,
            strtolower(trim($code)),
            $this->page->normalizeMode($request->query('view')),
        );
    }

    public function print(Request $request, string $code, ResolveRepairOrderPortalAccessAction $resolve): Response
    {
        $repairOrder = $this->repairOrderWithFindings($code, $resolve);
        $mode = $this->page->normalizeMode($request->query('view'));
        $liveUrl = route('portal.repair.inspection.show', ['code' => strtolower(trim($code))]);
        $html = $this->page->printHtml($repairOrder, $mode, $liveUrl, null);

        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public function pdf(Request $request, string $code, ResolveRepairOrderPortalAccessAction $resolve): Response
    {
        $repairOrder = $this->repairOrderWithFindings($code, $resolve);
        $mode = $this->page->normalizeMode($request->query('view'));
        $liveUrl = route('portal.repair.inspection.show', ['code' => strtolower(trim($code))]);
        $html = $this->page->printHtml($repairOrder, $mode, $liveUrl, null);

        try {
            $bytes = $this->pdf->toPdfBytes($html);
        } catch (Throwable) {
            abort(503, 'Inspection PDF could not be generated.');
        }

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="inspection-ro-'.$repairOrder->repair_order_id.'.pdf"',
        ]);
    }

    public function photo(
        string $code,
        InspectionItemPhoto $photo,
        ResolveRepairOrderPortalAccessAction $resolve,
    ): StreamedResponse {
        $repairOrder = $this->repairOrderWithFindings($code, $resolve);

        $photo->loadMissing('item.inspection');
        abort_unless(
            (int) $photo->item?->inspection?->repair_order_id === (int) $repairOrder->id,
            404,
        );
        abort_unless(InspectionCustomerEvidenceAllowlist::includes($photo->purpose), 404);
        abort_unless(Storage::disk('local')->exists($photo->storage_path), 404);

        return Storage::disk('local')->response(
            $photo->storage_path,
            $photo->original_name ?: 'evidence',
            ['Content-Type' => $photo->content_type ?: 'application/octet-stream'],
        );
    }

    private function repairOrderWithFindings(string $code, ResolveRepairOrderPortalAccessAction $resolve): RepairOrder
    {
        $access = $resolve->byPublicCode($code);
        abort_unless($access !== null, 404);

        $repairOrder = $access->repairOrder()
            ->with(['customer', 'vehicle'])
            ->firstOrFail();

        abort_unless(
            InspectionFindingCardProjection::recordedCountForRepairOrder($repairOrder) > 0,
            404,
        );

        return $repairOrder;
    }
}
