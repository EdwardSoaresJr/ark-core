<?php

namespace App\Ark\Operations\Portal;

use App\Ark\Operations\Documents\HtmlPdfBuilder;
use App\Ark\Operations\Inspections\InspectionFindingCardProjection;
use App\Ark\Operations\Inspections\InspectionReportProjection;
use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

/**
 * Staff print / PDF for an RO inspection report (browser print + shareable QR).
 */
final class RepairOrderInspectionPrintController
{
    public function __construct(
        private readonly PortalInspectionPage $page,
        private readonly CreateOrReuseRepairOrderPortalAccessAction $portalAccess,
        private readonly HtmlPdfBuilder $pdf,
    ) {}

    public function print(Request $request, RepairOrder $repairOrder): Response
    {
        $this->assertHasFindings($repairOrder);
        $mode = $this->page->normalizeMode($request->query('view') ?: InspectionReportProjection::MODE_SIMPLE);
        [$liveUrl] = $this->liveReport($repairOrder, $request->user());
        $html = $this->page->printHtml($repairOrder, $mode, $liveUrl, null);

        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public function pdf(Request $request, RepairOrder $repairOrder): Response
    {
        $this->assertHasFindings($repairOrder);
        $mode = $this->page->normalizeMode($request->query('view') ?: InspectionReportProjection::MODE_DETAILED);
        [$liveUrl] = $this->liveReport($repairOrder, $request->user());
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

    private function assertHasFindings(RepairOrder $repairOrder): void
    {
        abort_unless(
            InspectionFindingCardProjection::recordedCountForRepairOrder($repairOrder) > 0,
            404,
            'Record inspection findings before printing.',
        );
    }

    /**
     * @return array{0: string}
     */
    private function liveReport(RepairOrder $repairOrder, mixed $actor): array
    {
        $access = $this->portalAccess->execute($repairOrder, $actor instanceof \App\Models\User ? $actor : null);
        $url = route('portal.repair.inspection.show', ['code' => $access->public_code]);

        return [$url];
    }
}
