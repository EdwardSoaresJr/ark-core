<?php

namespace App\Ark\Operations\RepairOrders;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RepairOrderAuthorizationExceptionController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        RepairOrderConcern $concern,
        RepairOrderConcurrency $concurrency,
        RecordAuthorizationExceptionAction $recordException,
    ): RedirectResponse {
        abort_unless($concern->repair_order_id === $repairOrder->id, 404);
        $repairOrder->ensureOpenForEditing();
        $concurrency->guard($request, $repairOrder);

        $data = $request->validate([
            'reason' => ['required', Rule::enum(AuthorizationExceptionReason::class)],
            'note' => ['required', 'string', 'max:2000'],
            'line_ids' => ['required', 'array', 'min:1'],
            'line_ids.*' => ['integer'],
        ]);

        $recordException->execute(
            $repairOrder,
            $concern,
            $data['line_ids'],
            AuthorizationExceptionReason::from($data['reason']),
            $data['note'],
            $request->user(),
        );

        return redirect()
            ->route('operations.repair-orders.show', $repairOrder)
            ->withFragment('concern-'.$concern->id)
            ->with('status', 'Exception recorded for the selected lines. This does not approve the work.');
    }
}
