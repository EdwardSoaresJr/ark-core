<?php

namespace App\Ark\Operations\Portal;

use App\Ark\Operations\Documents\HtmlPdfBuilder;
use App\Ark\Operations\Inspections\InspectionItemPhoto;
use App\Ark\Operations\Inspections\InspectionReportProjection;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Vehicles\Vehicle;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

final class PortalVehicleInspectionController
{
    public function __construct(
        private readonly PortalInspectionPage $page,
        private readonly HtmlPdfBuilder $pdf,
    ) {}

    public function show(Request $request, Vehicle $vehicle, RepairOrder $repairOrder): View
    {
        $this->assertAccess($request, $vehicle, $repairOrder);

        return $this->page->renderAuth(
            $repairOrder,
            $this->page->normalizeMode($request->query('view')),
        );
    }

    public function print(Request $request, Vehicle $vehicle, RepairOrder $repairOrder): Response
    {
        $this->assertAccess($request, $vehicle, $repairOrder);
        $mode = $this->page->normalizeMode($request->query('view'));
        $share = $this->page->safeShare($repairOrder);
        $html = $this->page->printHtml($repairOrder, $mode, $share['url'], $share['plain_token']);

        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public function pdf(Request $request, Vehicle $vehicle, RepairOrder $repairOrder): Response
    {
        $this->assertAccess($request, $vehicle, $repairOrder);
        $mode = $this->page->normalizeMode($request->query('view'));
        $share = $this->page->safeShare($repairOrder);
        $html = $this->page->printHtml($repairOrder, $mode, $share['url'], $share['plain_token']);

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

    public function photo(Request $request, Vehicle $vehicle, RepairOrder $repairOrder, InspectionItemPhoto $photo): StreamedResponse
    {
        $this->assertAccess($request, $vehicle, $repairOrder);

        $photo->loadMissing('item.inspection');
        abort_unless(
            (int) $photo->item?->inspection?->repair_order_id === (int) $repairOrder->id,
            404,
        );
        abort_unless(\App\Ark\Operations\Inspections\InspectionCustomerEvidenceAllowlist::includes($photo->purpose), 404);
        abort_unless(Storage::disk('local')->exists($photo->storage_path), 404);

        return Storage::disk('local')->response(
            $photo->storage_path,
            $photo->original_name ?: 'evidence',
            ['Content-Type' => $photo->content_type ?: 'application/octet-stream'],
        );
    }

    private function assertAccess(Request $request, Vehicle $vehicle, RepairOrder $repairOrder): void
    {
        $customer = $request->user('portal');
        abort_unless($customer !== null, 403);
        abort_unless((int) $vehicle->customer_id === (int) $customer->id, 403);
        abort_unless((int) $repairOrder->vehicle_id === (int) $vehicle->id, 404);
        abort_unless((int) $repairOrder->customer_id === (int) $customer->id, 403);
    }
}
