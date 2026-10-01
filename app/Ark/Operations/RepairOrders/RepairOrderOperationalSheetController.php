<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Documents\HtmlPdfBuilder;
use App\Ark\Operations\Documents\OperationalSheetPresenter;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

class RepairOrderOperationalSheetController
{
    public function intakePdf(RepairOrder $repairOrder, OperationalSheetPresenter $presenter, HtmlPdfBuilder $pdf): Response|RedirectResponse
    {
        return $this->pdfResponse(
            $repairOrder,
            'operations.documents.sheets.intake',
            $presenter->intake($repairOrder),
            sprintf('intake-ro-%d.pdf', $repairOrder->repair_order_id),
            $pdf,
        );
    }

    public function techPdf(
        Request $request,
        RepairOrder $repairOrder,
        OperationalSheetPresenter $presenter,
        HtmlPdfBuilder $pdf,
    ): Response|RedirectResponse {
        $owner = null;
        if ($request->filled('owner')) {
            $owner = User::query()->findOrFail((int) $request->integer('owner'));
        }

        $sheet = $presenter->tech($repairOrder, $owner);
        $filename = $owner !== null
            ? sprintf('tech-ro-%d-owner-%d.pdf', $repairOrder->repair_order_id, $owner->id)
            : sprintf('tech-ro-%d.pdf', $repairOrder->repair_order_id);

        return $this->pdfResponse(
            $repairOrder,
            'operations.documents.sheets.tech',
            $sheet,
            $filename,
            $pdf,
        );
    }

    /**
     * @param  array<string, mixed>  $sheet
     */
    private function pdfResponse(
        RepairOrder $repairOrder,
        string $view,
        array $sheet,
        string $filename,
        HtmlPdfBuilder $pdf,
    ): Response|RedirectResponse {
        try {
            $html = view($view, ['sheet' => $sheet])->render();
            $bytes = $pdf->toPdfBytes($html);
        } catch (Throwable) {
            return redirect()
                ->back()
                ->with('error', 'Sheet PDF could not be generated. Check Chromium runtime support.');
        }

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }
}
