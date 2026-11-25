<?php

namespace App\Ark\Operations\Inspections;

use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class RepairOrderInspectionWaiverController
{
    public function store(
        Request $request,
        RepairOrder $repairOrder,
        WaiveRepairOrderInspectionAction $action,
    ): RedirectResponse {
        abort_unless(WaiveRepairOrderInspectionAction::canWaive($request->user()), 403);

        $data = $request->validate([
            'inspection_waived_reason' => ['required', 'string', 'min:8', 'max:500'],
        ]);

        $action->execute($repairOrder, $request->user(), $data['inspection_waived_reason']);

        return redirect()
            ->to(InspectionWorkspaceUrl::advisorReview($repairOrder))
            ->with('status', 'Required inspection waived for this repair order.');
    }

    public function destroy(
        Request $request,
        RepairOrder $repairOrder,
        WaiveRepairOrderInspectionAction $action,
    ): RedirectResponse {
        abort_unless(WaiveRepairOrderInspectionAction::canWaive($request->user()), 403);

        $action->clear($repairOrder, $request->user());

        return redirect()
            ->to(InspectionWorkspaceUrl::advisorReview($repairOrder))
            ->with('status', 'Inspection waiver cleared.');
    }
}
