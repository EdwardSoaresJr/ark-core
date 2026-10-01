<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\RepairOrders\RecordsRepairOrderEstimateMutation;
use App\Ark\Operations\Documents\EstimateDocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RepairOrderWorkGroupDestroyController
{
    use RecordsRepairOrderEstimateMutation;

    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        RepairOrderWorkGroup $workGroup,
        EstimateDocumentService $documents,
        RepairOrderConcurrency $concurrency,
    ): RedirectResponse {
        $workGroup->loadMissing('concern');
        abort_unless($workGroup->concern?->repair_order_id === $repairOrder->id, 404);

        $repairOrder->ensureOpenForEditing();
        $concurrency->guard($request, $repairOrder);

        $workGroup->lines()->update(['repair_order_work_group_id' => null]);
        $workGroup->delete();

        $documents->markDirtyForRepairOrder($repairOrder);
        $this->recordRepairOrderEstimateMutation($repairOrder, $request->user());

        return redirect()
            ->route('operations.repair-orders.show', $repairOrder)
            ->withFragment('estimate-lines')
            ->with('status', 'Repair removed. Lines kept ungrouped.');
    }
}
