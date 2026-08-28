<?php

namespace App\Ark\Operations\RepairOrders;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RepairOrderConcernDispositionController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        RepairOrderConcern $concern,
        RepairOrderConcurrency $concurrency,
        UpdateConcernDispositionAction $updateDisposition,
    ): RedirectResponse|JsonResponse
    {
        abort_unless($concern->repair_order_id === $repairOrder->id, 404);
        $repairOrder->ensureOpenForEditing();
        $concurrency->guard($request, $repairOrder);

        $data = $request->validate([
            'disposition' => ['required', Rule::enum(RepairOrderConcernDisposition::class)],
        ]);

        $updateDisposition->execute(
            $repairOrder,
            $concern,
            RepairOrderConcernDisposition::from($data['disposition']),
            $request->user(),
        );

        $concern->refresh();

        if ($request->expectsJson()) {
            return response()->json([
                'disposition' => $concern->disposition->value,
                'label' => $concern->disposition->label(),
                'estimate_version' => $concurrency->openedVersion($repairOrder),
            ]);
        }

        return redirect()
            ->route('operations.repair-orders.show', $repairOrder)
            ->withFragment('concern-'.$concern->id)
            ->with('status', 'Concern outcome updated.');
    }
}
