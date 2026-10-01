<?php

namespace App\Ark\Operations\Inspections;

use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RepairOrderInspectionItemUpdateController
{
    use ValidatesInspectionScope;

    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        InspectionItem $item,
        EnsureInspectionAction $ensureInspection,
    ): RedirectResponse {
        $repairOrder->ensureOpenForEditing();
        $item = $this->itemForRepairOrder($repairOrder, $item);
        $inspection = $ensureInspection->execute($repairOrder, $request->user());

        $data = $request->validate([
            'observed_state' => ['required', Rule::enum(InspectionObservedState::class)],
            'notes' => ['nullable', 'string', 'max:5000'],
            'repair_order_concern_id' => ['nullable', 'integer'],
        ]);

        $item->update([
            'observed_state' => $data['observed_state'],
            'notes' => $data['notes'] ?? null,
            'repair_order_concern_id' => $this->resolveConcernIdForRepairOrder(
                $repairOrder,
                isset($data['repair_order_concern_id']) ? (int) $data['repair_order_concern_id'] : null,
            ),
        ]);

        $this->touchInspectionRecorded($inspection, $request->user());

        return $this->redirectToFinding($repairOrder, $item, 'Finding updated.');
    }
}
