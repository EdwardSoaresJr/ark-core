<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Maintenance\CancelEngineOilServiceAction;
use App\Ark\Operations\Maintenance\MaintenanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RepairOrderLineDestroyController
{
    use RecordsRepairOrderEstimateMutation;

    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        RepairOrderLine $line,
        RepairOrderConcurrency $concurrency,
        CancelEngineOilServiceAction $cancelEngineOil,
        DestroyRepairOrderLine $destroyLine,
    ): RedirectResponse {
        abort_unless($line->repair_order_id === $repairOrder->id, 404);
        $repairOrder->ensureOpenForEditing();
        $concurrency->guard($request, $repairOrder);

        $maintenance = MaintenanceService::query()
            ->where('repair_order_line_id', $line->id)
            ->first();

        if ($maintenance !== null && ! $maintenance->hasConfirmedEvent()) {
            $cancelEngineOil->handle($maintenance);

            $this->recordRepairOrderEstimateMutation($repairOrder, $request->user());

            return redirect()
                ->route('operations.repair-orders.show', $repairOrder)
                ->withFragment('estimate-lines')
                ->with('status', 'Engine oil service removed.');
        }

        $destroyLine->destroy($repairOrder, $line, $request->user());

        return redirect()
            ->route('operations.repair-orders.show', $repairOrder)
            ->withFragment('estimate-lines')
            ->with('status', 'Saved');
    }
}
