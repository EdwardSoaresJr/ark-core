<?php

namespace App\Ark\Operations\Recommendations;

use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class DismissRecommendationController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        Recommendation $recommendation,
        DismissRecommendationAction $action,
    ): RedirectResponse {
        abort_unless(
            (int) $recommendation->vehicle_id === (int) $repairOrder->vehicle_id
            && (int) $recommendation->customer_id === (int) $repairOrder->customer_id,
            404,
        );

        $validated = $request->validate([
            'dismissal_reason' => ['nullable', 'string', 'max:64'],
            'note' => ['nullable', 'string', 'max:4000'],
        ]);

        $action->handle(
            $recommendation,
            actor: $request->user(),
            reason: $validated['dismissal_reason'] ?? null,
            note: $validated['note'] ?? null,
        );

        return back()->with('status', 'Recommendation dismissed.');
    }
}
