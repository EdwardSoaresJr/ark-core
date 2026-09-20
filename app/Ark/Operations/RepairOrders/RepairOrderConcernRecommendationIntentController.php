<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Documents\EstimateDocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RepairOrderConcernRecommendationIntentController
{
    use RecordsRepairOrderEstimateMutation;

    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        RepairOrderConcern $concern,
        EstimateDocumentService $documents,
        RepairOrderConcurrency $concurrency,
    ): RedirectResponse {
        abort_unless($concern->repair_order_id === $repairOrder->id, 404);

        $repairOrder->ensureOpenForEditing();
        $concurrency->guard($request, $repairOrder);

        $data = $request->validate([
            'recommendation_intent' => ['required', Rule::enum(RecommendationIntent::class)],
        ]);

        $concern->update([
            'recommendation_intent' => $data['recommendation_intent'],
        ]);

        $documents->markDirtyForRepairOrder($repairOrder);
        $this->recordRepairOrderEstimateMutation($repairOrder, $request->user());

        return redirect()
            ->route('operations.repair-orders.show', $repairOrder)
            ->withFragment('concern-'.$concern->id)
            ->with('status', 'Recommendation intent updated for '.$concern->summary.'.');
    }
}
