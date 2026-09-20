<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Documents\EstimateDocumentService;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RepairOrderConcernBillingPostureController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        RepairOrderConcern $concern,
        EstimateTotalsCalculator $calculator,
        EstimateDocumentService $documents,
        RepairOrderConcurrency $concurrency,
    ): RedirectResponse|JsonResponse {
        abort_unless($concern->repair_order_id === $repairOrder->id, 404);

        $repairOrder->ensureOpenForEditing();
        $concurrency->guard($request, $repairOrder);

        $data = $request->validate([
            'billing_posture' => ['required', Rule::enum(ConcernBillingPosture::class)],
        ]);

        $concern->update([
            'billing_posture' => $data['billing_posture'],
        ]);

        $calculator->recalculateRepairOrder($repairOrder);
        $documents->markDirtyForRepairOrder($repairOrder);

        if ($request->expectsJson()) {
            return response()->json([
                'billing_posture' => $concern->billing_posture->value,
                'label' => $concern->billing_posture->label(),
            ]);
        }

        return redirect()
            ->route('operations.repair-orders.show', $repairOrder)
            ->withFragment('estimate-lines')
            ->with('status', 'Billing updated for '.$concern->summary.'.');
    }
}
