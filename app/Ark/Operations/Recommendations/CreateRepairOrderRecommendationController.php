<?php

namespace App\Ark\Operations\Recommendations;

use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class CreateRepairOrderRecommendationController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        CreateRecommendationAction $action,
    ): RedirectResponse {
        $repairOrder->loadMissing(['customer', 'vehicle']);

        if ($repairOrder->customer === null || $repairOrder->vehicle === null) {
            throw ValidationException::withMessages([
                'recommendation' => 'Customer and vehicle are required before recording a recommendation.',
            ]);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'customer_description' => ['nullable', 'string', 'max:4000'],
            'advisor_note' => ['nullable', 'string', 'max:4000'],
            'safety_related' => ['sometimes', 'boolean'],
            'urgency' => ['nullable', Rule::enum(RecommendationUrgency::class)],
            'due_kind' => ['nullable', Rule::enum(RecommendationDueKind::class)],
            'due_on' => ['nullable', 'date'],
            'due_mileage' => ['nullable', 'integer', 'min:0'],
            'follow_up_at' => ['nullable', 'date'],
        ]);

        $recommendation = $action->handle([
            'customer' => $repairOrder->customer,
            'vehicle' => $repairOrder->vehicle,
            'title' => $validated['title'],
            'customer_description' => $validated['customer_description'] ?? null,
            'advisor_note' => $validated['advisor_note'] ?? null,
            'originating_repair_order' => $repairOrder,
            'discovered_mileage' => $repairOrder->resolvedMileageIn(),
            'urgency' => $validated['urgency'] ?? null,
            'safety_related' => (bool) ($validated['safety_related'] ?? false),
            'due_kind' => $validated['due_kind'] ?? null,
            'due_on' => $validated['due_on'] ?? null,
            'due_mileage' => $validated['due_mileage'] ?? null,
            'follow_up_at' => $validated['follow_up_at'] ?? null,
            'follow_up_owner_user_id' => $request->user()?->id,
            'source_kind' => RecommendationSourceKind::Advisor,
        ]);

        return back()->with('status', 'Recommendation recorded: '.$recommendation->title);
    }
}
