<?php

namespace App\Ark\Operations\RepairOrders;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RepairOrderConcernMoveToNewRepairOrderController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        RepairOrderConcern $concern,
        MoveRepairOrderConcernToNewRepairOrderAction $action,
        RepairOrderConcurrency $concurrency,
    ): RedirectResponse {
        abort_unless($concern->repair_order_id === $repairOrder->id, 404);

        $concurrency->guard($request, $repairOrder);

        $newRepairOrder = $action->execute($repairOrder, $concern, $request->user());

        return redirect()
            ->route('operations.repair-orders.show', $newRepairOrder)
            ->with('status', 'Scope moved to RO #'.$newRepairOrder->repair_order_id.'.');
    }
}
