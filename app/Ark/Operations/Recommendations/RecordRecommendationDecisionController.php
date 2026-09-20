<?php

namespace App\Ark\Operations\Recommendations;

use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class RecordRecommendationDecisionController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        Recommendation $recommendation,
        RecordRecommendationDecisionAction $action,
    ): RedirectResponse {
        abort_unless(
            (int) $recommendation->vehicle_id === (int) $repairOrder->vehicle_id
            && (int) $recommendation->customer_id === (int) $repairOrder->customer_id,
            404,
        );

        $validated = $request->validate([
            'decision' => ['required', Rule::in([
                RecommendationEventType::Approved->value,
                RecommendationEventType::Declined->value,
                RecommendationEventType::Deferred->value,
            ])],
            'reason_code' => ['nullable', Rule::enum(RecommendationDecisionReason::class)],
            'note' => ['nullable', 'string', 'max:4000'],
            'follow_up_at' => ['nullable', 'date'],
        ]);

        $action->handle(
            $recommendation,
            RecommendationEventType::from($validated['decision']),
            $repairOrder,
            actor: $request->user(),
            reason: RecommendationDecisionReason::optionalFrom($validated['reason_code'] ?? null),
            note: $validated['note'] ?? null,
            followUpAt: $validated['follow_up_at'] ?? null,
            followUpOwnerUserId: $request->user()?->id,
        );

        return back()->with('status', 'Recommendation decision recorded.');
    }
}
