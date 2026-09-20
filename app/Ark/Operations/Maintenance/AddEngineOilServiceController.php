<?php

namespace App\Ark\Operations\Maintenance;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcurrency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class AddEngineOilServiceController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        AddEngineOilServiceAction $action,
        RepairOrderConcurrency $concurrency,
    ): RedirectResponse {
        $concurrency->guard($request, $repairOrder);

        $result = $action->handle(
            $repairOrder,
            $request->user(),
            resetReminder: $request->boolean('reset_reminder', true),
        );

        return redirect()
            ->route('operations.repair-orders.show', $repairOrder)
            ->with('status', 'Saved')
            ->withFragment('maintenance-engine-oil-'.$result['service']->id);
    }
}
