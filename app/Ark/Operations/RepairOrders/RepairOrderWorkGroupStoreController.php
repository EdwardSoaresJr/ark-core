<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\RepairOrders\RecordsRepairOrderEstimateMutation;
use App\Ark\Operations\Documents\EstimateDocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RepairOrderWorkGroupStoreController
{
    use RecordsRepairOrderEstimateMutation;

    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        RepairOrderConcern $concern,
        EstimateDocumentService $documents,
        RepairOrderConcurrency $concurrency,
    ): RedirectResponse {
        abort_unless($concern->repair_order_id === $repairOrder->id, 404);

        $repairOrder->ensureOpenForEditing();
        $concurrency->guard($request, $repairOrder);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
        ]);

        $workGroup = $concern->workGroups()->create([
            'title' => $data['title'],
            'position' => ((int) $concern->workGroups()->max('position')) + 1,
            'owner_type' => RepairActionOwnerType::Technician,
            'owner_user_id' => null,
        ]);

        app(AssignRepairActionOwnerAction::class)->seedFromPrimaryTechnician(
            $workGroup,
            $repairOrder,
            $request->user(),
        );

        $documents->markDirtyForRepairOrder($repairOrder);
        $this->recordRepairOrderEstimateMutation($repairOrder, $request->user());

        return redirect()
            ->route('operations.repair-orders.show', $repairOrder)
            ->withFragment('repair-action-'.$workGroup->id)
            ->with('status', 'Repair added.');
    }
}
