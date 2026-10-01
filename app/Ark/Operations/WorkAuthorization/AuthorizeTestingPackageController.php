<?php

namespace App\Ark\Operations\WorkAuthorization;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcurrency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class AuthorizeTestingPackageController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        AuthorizeTestingPackageAction $action,
        RepairOrderConcurrency $concurrency,
    ): RedirectResponse {
        $concurrency->guard($request, $repairOrder);

        $validated = $request->validate([
            'repair_order_concern_id' => ['nullable', 'integer', 'exists:repair_order_concerns,id'],
        ]);

        $concern = null;
        if (isset($validated['repair_order_concern_id'])) {
            $concern = RepairOrderConcern::query()->findOrFail((int) $validated['repair_order_concern_id']);
        }

        $action->handle(
            $repairOrder,
            $request->user(),
            $concern,
        );

        return redirect()
            ->to(route('operations.repair-orders.show', $repairOrder).'#work-authorization')
            ->with('status', 'Testing Package authorized. Repair Action is ready for work.');
    }
}
