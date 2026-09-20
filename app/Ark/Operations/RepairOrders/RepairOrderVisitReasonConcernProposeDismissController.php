<?php

namespace App\Ark\Operations\RepairOrders;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class RepairOrderVisitReasonConcernProposeDismissController
{
    public function __invoke(Request $request, RepairOrder $repairOrder): RedirectResponse
    {
        $repairOrder->ensureOpenForEditing();

        $request->session()->put(
            RepairOrderVisitReasonConcernAcceptController::dismissKey($repairOrder),
            true,
        );

        return redirect()
            ->route('operations.repair-orders.show', $repairOrder)
            ->withFragment('visit-reason')
            ->with('status', 'Visit reason suggestions dismissed. Add concerns manually when ready.');
    }
}
