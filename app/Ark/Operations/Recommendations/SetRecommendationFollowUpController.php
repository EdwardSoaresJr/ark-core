<?php

namespace App\Ark\Operations\Recommendations;

use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class SetRecommendationFollowUpController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        Recommendation $recommendation,
        SetRecommendationFollowUpAction $action,
    ): RedirectResponse {
        abort_unless(
            (int) $recommendation->vehicle_id === (int) $repairOrder->vehicle_id
            && (int) $recommendation->customer_id === (int) $repairOrder->customer_id,
            404,
        );

        $validated = $request->validate([
            'action' => ['required', 'in:schedule,complete,snooze'],
            'follow_up_at' => ['nullable', 'date', 'required_if:action,schedule'],
            'snooze_until' => ['nullable', 'date', 'required_if:action,snooze'],
            'note' => ['nullable', 'string', 'max:4000'],
        ]);

        $actor = $request->user();

        match ($validated['action']) {
            'complete' => $action->complete($recommendation, $actor, $validated['note'] ?? null),
            'snooze' => $action->snooze($recommendation, $validated['snooze_until'], $actor, $validated['note'] ?? null),
            default => $action->schedule(
                $recommendation,
                $validated['follow_up_at'],
                $actor,
                $actor?->id,
                $validated['note'] ?? null,
            ),
        };

        return back()->with('status', 'Follow-up updated.');
    }
}
