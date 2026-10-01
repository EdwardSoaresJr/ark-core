<?php

namespace App\Ark\Operations\Inspections;

use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RepairOrderInspectionResetController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        EnsureInspectionAction $ensureInspection,
        ResetInspectionWalkAction $resetWalk,
    ): RedirectResponse {
        abort_unless(ResetInspectionWalkAction::canReset($request->user()), 403);

        $request->validate([
            'confirm' => ['required', 'accepted'],
        ]);

        $repairOrder->ensureOpenForEditing();
        $inspection = $ensureInspection->execute($repairOrder, $request->user());
        $resetWalk->execute($repairOrder, $inspection, $request->user());

        return redirect()
            ->to(InspectionWorkspaceUrl::advisorReview($repairOrder))
            ->with('status', 'Inspection walk reset. Conditions, measurements, and walk photos were cleared. Other findings were kept.');
    }
}
