<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\RepairOrders\RecordsRepairOrderEstimateMutation;
use App\Ark\Operations\Documents\EstimateDocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RepairOrderConcernUpdateController
{
    use RecordsRepairOrderEstimateMutation;
    public function __invoke(Request $request, RepairOrder $repairOrder, RepairOrderConcern $concern, EstimateDocumentService $documents, RepairOrderConcurrency $concurrency): RedirectResponse
    {
        abort_unless($concern->repair_order_id === $repairOrder->id, 404);
        $repairOrder->ensureOpenForEditing();
        $concurrency->guard($request, $repairOrder);

        $data = $request->validate([
            'summary' => ['required', 'string', 'max:2000'],
            'customer_states' => ['nullable', 'string'],
            'verified_findings' => ['nullable', 'string'],
            'dtcs_summary' => ['nullable', 'string'],
            'recommendation' => ['nullable', 'string'],
            'recommendation_intent' => ['required', Rule::enum(RecommendationIntent::class)],
            'return_mode' => ['nullable', Rule::in(['review'])],
        ]);

        unset($data['return_mode']);

        $concern->update($data);
        $documents->markDirtyForRepairOrder($repairOrder);

        $this->recordRepairOrderEstimateMutation($repairOrder, $request->user());

        return redirect()
            ->route('operations.repair-orders.show', $repairOrder)
            ->withFragment('estimate-lines')
            ->with('status', 'Concern narrative updated.');
    }
}
