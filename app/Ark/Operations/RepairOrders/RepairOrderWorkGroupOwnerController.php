<?php

namespace App\Ark\Operations\RepairOrders;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RepairOrderWorkGroupOwnerController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        RepairOrderWorkGroup $workGroup,
        AssignRepairActionOwnerAction $assignOwner,
        RepairOrderConcurrency $concurrency,
    ): RedirectResponse {
        abort_unless((int) $workGroup->concern?->repair_order_id === (int) $repairOrder->id, 404);

        $repairOrder->ensureOpenForEditing();
        $concurrency->guard($request, $repairOrder);

        $data = $request->validate([
            'owner_user_id' => ['required', 'integer', 'exists:users,id'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $assignOwner->assign(
            $workGroup,
            (int) $data['owner_user_id'],
            $request->user(),
            $data['reason'] ?? null,
        );

        return redirect()
            ->route('operations.repair-orders.show', $repairOrder)
            ->withFragment('repair-action-'.$workGroup->id)
            ->with('status', 'Repair Action owner updated.');
    }
}
