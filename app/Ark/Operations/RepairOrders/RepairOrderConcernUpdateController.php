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
            'recommendation_intent' => ['nullable', Rule::enum(RecommendationIntent::class)],
            'recommendation_posture' => ['nullable', Rule::in(['must_do', 'should_do', 'could_do', 'information_only'])],
            'return_mode' => ['nullable', Rule::in(['review'])],
        ]);

        unset($data['return_mode']);

        if ($request->exists('recommendation_posture')) {
            $posture = (string) ($data['recommendation_posture'] ?? '');
            unset($data['recommendation_posture']);

            if ($posture === '' || $posture === 'information_only') {
                $data['information_only'] = $posture === 'information_only';
                $data['recommendation_priority'] = null;
            } else {
                $data['information_only'] = false;
                $data['recommendation_priority'] = $posture;
            }
        } else {
            unset($data['recommendation_posture']);
        }

        $concern->update($data);
        $documents->markDirtyForRepairOrder($repairOrder);

        $this->recordRepairOrderEstimateMutation($repairOrder, $request->user());

        return redirect()
            ->route('operations.repair-orders.show', $repairOrder)
            ->withFragment('estimate-lines')
            ->with('status', 'Concern narrative updated.');
    }
}
