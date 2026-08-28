<?php

namespace App\Ark\Operations\Inspections;

use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RepairOrderInspectionItemStoreController
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
            'category' => ['required', Rule::enum(InspectionItemCategory::class)],
            'label' => ['required', 'string', 'max:191'],
            'observed_state' => ['nullable', Rule::enum(InspectionObservedState::class)],
            'notes' => ['nullable', 'string', 'max:5000'],
            'repair_order_concern_id' => ['nullable', 'integer'],
        ]);

        $nextPosition = (int) $inspection->items()->max('position') + 1;

        $item = $inspection->items()->create([
            'category' => $data['category'],
            'label' => $data['label'],
            'observed_state' => $data['observed_state'] ?? InspectionObservedState::NotChecked->value,
            'notes' => $data['notes'] ?? null,
            'repair_order_concern_id' => $this->resolveConcernIdForRepairOrder(
                $repairOrder,
                isset($data['repair_order_concern_id']) ? (int) $data['repair_order_concern_id'] : null,
            ),
            'position' => $nextPosition,
        ]);

        $this->touchInspectionRecorded($inspection, $request->user());

        return $this->redirectToFinding($repairOrder, $item, 'Finding added.');
    }
}
