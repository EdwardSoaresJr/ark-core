<?php

namespace App\Ark\Operations\Maintenance;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcurrency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class AddExtraOilQuartsAtCostController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        MaintenanceService $maintenanceService,
        AddExtraOilQuartsAtCostAction $action,
        RepairOrderConcurrency $concurrency,
    ): RedirectResponse {
        abort_unless(
            (int) $maintenanceService->repair_order_id === (int) $repairOrder->id
            && $maintenanceService->kind === MaintenanceServiceKind::EngineOil,
            404,
        );

        $concurrency->guard($request, $repairOrder);

        $data = $request->validate([
            'quarts' => ['required', 'numeric', 'min:0.1', 'max:99.99'],
            'cost_per_quart' => ['required', 'numeric', 'min:0', 'max:999.99'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $line = $action->handle(
            $maintenanceService,
            $data['quarts'],
            $data['cost_per_quart'],
            $data['description'] ?? null,
        );

        return redirect()
            ->route('operations.repair-orders.show', $repairOrder)
            ->with('status', 'Extra quarts added at cost.')
            ->withFragment('line-'.$line->id);
    }
}
