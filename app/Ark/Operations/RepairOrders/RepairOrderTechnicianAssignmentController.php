<?php

namespace App\Ark\Operations\RepairOrders;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RepairOrderTechnicianAssignmentController
{
    use RecordsRepairOrderEstimateMutation;

    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        RepairOrderConcurrency $concurrency,
        AssignRepairOrderTechnician $assignment,
    ): RedirectResponse {
        $repairOrder->ensureOpenForEditing();
        $concurrency->guard($request, $repairOrder);

        $technicianId = $assignment->validate($request->all());

        if ((int) $repairOrder->assigned_technician_id === $technicianId) {
            return redirect()
                ->back()
                ->with('status', 'Technician assignment unchanged.');
        }

        $technician = $assignment->assign($repairOrder, $technicianId, $request->user());

        $this->recordRepairOrderEstimateMutation($repairOrder, $request->user());

        return redirect()
            ->back()
            ->with('status', $technician ? 'Technician assigned: '.$technician->name.'.' : 'Technician assignment cleared.');
    }
}
