<?php

namespace App\Ark\Operations\RepairOrders;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RepairOrderVisitReasonUpdateController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        RepairOrderConcurrency $concurrency,
    ): RedirectResponse|JsonResponse {
        $repairOrder->ensureOpenForEditing();
        $concurrency->guard($request, $repairOrder);

        $data = $request->validate([
            'visit_reason' => ['nullable', 'string', 'max:5000'],
        ]);

        $visitReason = trim((string) ($data['visit_reason'] ?? ''));

        $repairOrder->forceFill([
            'visit_reason' => $visitReason !== '' ? $visitReason : null,
        ])->save();

        $status = 'Reason for visit updated.';

        if ($request->expectsJson()) {
            return response()->json([
                'status' => $status,
                'visit_reason' => $repairOrder->visit_reason,
            ]);
        }

        return redirect()
            ->back()
            ->withFragment('visit-reason')
            ->with('status', $status);
    }
}
