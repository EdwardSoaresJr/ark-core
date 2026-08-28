<?php

namespace App\Ark\Operations\RepairOrders;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Temporary GET bridge — old /edit, /builder, and /estimate-review URLs.
 *
 * Removal gate: after 2026-09-01 shop bookmark migration + zero hits in access logs.
 * Do not restore a competing Repair Order page here.
 */
final class RepairOrderLegacySurfaceRedirectController
{
    public function __invoke(Request $request, RepairOrder $repairOrder): RedirectResponse
    {
        return redirect()->route('operations.repair-orders.show', [
            'repairOrder' => $repairOrder,
            ...$request->query(),
        ]);
    }
}
