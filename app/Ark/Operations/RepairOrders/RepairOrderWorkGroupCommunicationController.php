<?php

namespace App\Ark\Operations\RepairOrders;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RepairOrderWorkGroupCommunicationController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        RepairOrderWorkGroup $workGroup,
        UpdateRepairActionCommunicationAction $updateCommunication,
        RepairOrderConcurrency $concurrency,
    ): RedirectResponse {
        abort_unless((int) $workGroup->concern?->repair_order_id === (int) $repairOrder->id, 404);

        $repairOrder->ensureOpenForEditing();
        $concurrency->guard($request, $repairOrder);

        $data = $request->validate([
            'status' => ['nullable', 'string', Rule::in(RepairActionStatus::values())],
            'latest_update' => ['nullable', 'string', 'max:2000'],
            'clear_latest_update' => ['sometimes', 'boolean'],
        ]);

        $updateCommunication->update(
            $workGroup,
            $request->user(),
            status: isset($data['status']) ? RepairActionStatus::from($data['status']) : null,
            latestUpdate: array_key_exists('latest_update', $data) ? ($data['latest_update'] ?? '') : null,
            clearLatestUpdate: (bool) ($data['clear_latest_update'] ?? false),
        );

        return redirect()
            ->route('operations.repair-orders.show', $repairOrder)
            ->withFragment('repair-action-'.$workGroup->id)
            ->with('status', 'Repair Action update saved.');
    }
}
