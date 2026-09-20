<?php

namespace App\Ark\Operations\Inspections;

use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RepairOrderInspectionNotesUpdateController
{
    use ValidatesInspectionScope;

    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        EnsureInspectionAction $ensureInspection,
    ): RedirectResponse {
        $repairOrder->ensureOpenForEditing();
        $inspection = $ensureInspection->execute($repairOrder, $request->user());

        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:10000'],
        ]);

        $inspection->update([
            'notes' => $data['notes'] ?? null,
        ]);

        $this->touchInspectionRecorded($inspection, $request->user());

        return redirect()
            ->to(InspectionWorkspaceUrl::show($repairOrder, [], 'inspection-notes'))
            ->with('status', 'Inspection notes saved.');
    }
}
