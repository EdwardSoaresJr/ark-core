<?php

namespace App\Ark\Operations\Recommendations;

use App\Ark\Operations\Inspections\InspectionItem;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class CreateInspectionRecommendationController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        InspectionItem $item,
        CreateRecommendationFromInspectionItemAction $action,
    ): RedirectResponse {
        $item->loadMissing('inspection');
        abort_unless((int) $item->inspection?->repair_order_id === (int) $repairOrder->id, 404);

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:180'],
            'customer_description' => ['nullable', 'string', 'max:4000'],
            'advisor_note' => ['nullable', 'string', 'max:4000'],
            'safety_related' => ['sometimes', 'boolean'],
            'due_kind' => ['nullable', Rule::enum(RecommendationDueKind::class)],
        ]);

        $recommendation = $action->handle($item, $repairOrder, $validated);

        return back()->with('status', 'Recommendation recorded: '.$recommendation->title);
    }
}
