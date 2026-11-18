<?php

namespace App\Ark\Operations\RepairOrders;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Retired (#217): billing is assigned on the customer and overridden per concern.
 * A repair order has no billing control. The routes stay so stale clients get a clear answer,
 * and repair_orders.default_billing_posture stays for historical reads only.
 */
class RepairOrderBillingPostureController
{
    private const MESSAGE = 'Billing is set on the customer\'s billing class. Override a single concern from its billing control.';

    public function preview(Request $request, RepairOrder $repairOrder): JsonResponse
    {
        return $this->retired();
    }

    public function update(Request $request, RepairOrder $repairOrder): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return $this->retired();
        }

        return redirect()
            ->route('operations.repair-orders.show', $repairOrder)
            ->with('error', self::MESSAGE);
    }

    private function retired(): JsonResponse
    {
        return response()->json(['message' => self::MESSAGE], 410);
    }
}
