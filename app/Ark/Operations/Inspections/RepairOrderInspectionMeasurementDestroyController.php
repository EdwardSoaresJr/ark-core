<?php

namespace App\Ark\Operations\Inspections;

use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RepairOrderInspectionMeasurementDestroyController
{
    use ValidatesInspectionScope;

    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        InspectionItemMeasurement $measurement,
        EnsureInspectionAction $ensureInspection,
    ): RedirectResponse {
        $repairOrder->ensureOpenForEditing();
        $measurement = $this->measurementForRepairOrder($repairOrder, $measurement);
        $item = $measurement->item;
        $inspection = $ensureInspection->execute($repairOrder, $request->user());

        $measurement->delete();

        $this->touchInspectionRecorded($inspection, $request->user());

        return $this->redirectToFinding($repairOrder, $item, 'Measurement removed.');
    }
}
