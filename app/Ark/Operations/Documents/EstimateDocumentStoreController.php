<?php

namespace App\Ark\Operations\Documents;

use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Events\OperationalEventRecorder;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcurrency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class EstimateDocumentStoreController
{
    public function __invoke(Request $request, RepairOrder $repairOrder, EstimateDocumentService $documents, OperationalEventRecorder $events, RepairOrderConcurrency $concurrency): RedirectResponse
    {
        $concurrency->guard($request, $repairOrder);
        $document = $documents->createOrRefresh($repairOrder, $request->user());
        $eventName = $document->wasRecentlyCreated
            ? OperationalEventName::EstimateDocumentGenerated
            : OperationalEventName::EstimateDocumentRefreshed;

        try {
            $documents->generatePdf($document);
        } catch (Throwable) {
            return redirect()
                ->route('operations.repair-orders.show', $repairOrder)
                ->with('status', 'Estimate snapshot saved, but PDF generation failed. Check Chromium runtime support.');
        }

        $document->refresh();
        $events->record(
            $eventName,
            $repairOrder,
            actor: $request->user(),
            payload: [
                'repair_order_id' => $repairOrder->repair_order_id,
                'estimate_document_id' => $document->id,
                'document_number' => $document->document_number,
                'status' => $document->status,
            ],
        );

        return redirect()
            ->route('operations.repair-orders.show', $repairOrder)
            ->with('status', 'Estimate PDF refreshed.');
    }
}
