<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Maintenance\MaintenanceService;
use App\Ark\Operations\WorkAuthorization\WorkAuthorization;
use App\Ark\Operations\WorkAuthorization\WorkAuthorizationStatus;
use App\Ark\Operations\RepairOrders\RecordsRepairOrderEstimateMutation;
use App\Ark\Operations\Documents\EstimateDocumentService;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Events\OperationalEventRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RepairOrderConcernDestroyController
{
    use RecordsRepairOrderEstimateMutation;
    public function __invoke(Request $request, RepairOrder $repairOrder, RepairOrderConcern $concern, EstimateDocumentService $documents, OperationalEventRecorder $events, RepairOrderConcurrency $concurrency): RedirectResponse
    {
        abort_unless($concern->repair_order_id === $repairOrder->id, 404);
        $repairOrder->ensureOpenForEditing();
        $concurrency->guard($request, $repairOrder);

        abort_if(
            $concern->lines()->exists(),
            422,
            'Delete or move concern lines before deleting the concern.',
        );

        $payload = [
            'concern_id' => $concern->id,
            'summary' => $concern->summary,
            'recommendation_intent' => $concern->recommendationIntent()->value,
            'position' => $concern->position,
        ];

        MaintenanceService::query()
            ->where('repair_order_concern_id', $concern->id)
            ->whereNull('current_event_id')
            ->get()
            ->each(fn (MaintenanceService $service) => $service->markCancelledOrphan());

        WorkAuthorization::query()
            ->where('repair_order_concern_id', $concern->id)
            ->where('status', '!=', WorkAuthorizationStatus::Completed->value)
            ->get()
            ->each(fn (WorkAuthorization $authorization) => $authorization->markCancelledOrphan());

        $concern->delete();
        $documents->markDirtyForRepairOrder($repairOrder);

        $events->record(
            OperationalEventName::ConcernDeleted,
            $repairOrder,
            actor: $request->user(),
            payload: $payload,
        );

        $this->recordRepairOrderEstimateMutation($repairOrder, $request->user());

        return redirect()
            ->route('operations.repair-orders.show', $repairOrder)
            ->withFragment('estimate-lines')
            ->with('status', 'Concern deleted.');
    }
}
