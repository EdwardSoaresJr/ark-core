<?php

namespace App\Ark\Operations\Recommendations;

use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class ResolveRecommendationController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        Recommendation $recommendation,
        ResolveRecommendationAction $action,
    ): RedirectResponse {
        abort_unless(
            (int) $recommendation->vehicle_id === (int) $repairOrder->vehicle_id
            && (int) $recommendation->customer_id === (int) $repairOrder->customer_id,
            404,
        );

        $validated = $request->validate([
            'resolved_reason' => ['required', Rule::enum(RecommendationResolvedReason::class)],
            'note' => ['nullable', 'string', 'max:4000'],
        ]);

        $action->handle(
            $recommendation,
            RecommendationResolvedReason::from($validated['resolved_reason']),
            actor: $request->user(),
            repairOrder: $repairOrder,
            resolvedMileage: $repairOrder->resolvedMileageIn(),
            note: $validated['note'] ?? null,
        );

        return back()->with('status', 'Recommendation resolved.');
    }
}
