<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Documents\EstimateDocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
            'recommendation_posture' => ['nullable', Rule::in(['must_do', 'should_do', 'could_do', 'information_only'])],
            'recommendation_priority' => ['nullable', Rule::enum(RecommendationPriority::class)],
            'information_only' => ['nullable', 'boolean'],
            'recommendation_intent' => ['nullable', Rule::enum(RecommendationIntent::class)],
        ]);

        $changesPriority = $request->exists('recommendation_posture')
            || $request->filled('recommendation_priority')
            || ($request->has('information_only') && ! $request->exists('recommendation_posture'));
        $changesSemantic = $request->exists('recommendation_intent');

        if (! $changesPriority && ! $changesSemantic) {
            throw ValidationException::withMessages([
                'recommendation_posture' => 'Choose a priority or work type.',
            ]);
        }

        if ($request->exists('recommendation_posture')) {
            $posture = (string) ($data['recommendation_posture'] ?? '');
            if ($posture === '') {
                $concern->information_only = false;
                $concern->recommendation_priority = null;
            } else {
                $this->applyPosture($concern, $posture);
            }
        } elseif ($request->filled('recommendation_priority')) {
            $concern->recommendation_priority = RecommendationPriority::from($data['recommendation_priority']);
            $concern->information_only = false;
        } elseif ($request->boolean('information_only')) {
            $concern->information_only = true;
            $concern->recommendation_priority = null;
        }

        if ($changesSemantic) {
            $concern->recommendation_intent = filled($data['recommendation_intent'] ?? null)
                ? RecommendationIntent::from($data['recommendation_intent'])
                : null;
        }

        $concern->save();

        $documents->markDirtyForRepairOrder($repairOrder);
        $this->recordRepairOrderEstimateMutation($repairOrder, $request->user());

        $label = $concern->presentation()->staffLabel()
            ?? $concern->recommendationIntent()?->staffLabel()
            ?? 'Priority';

        return redirect()
            ->route('operations.repair-orders.show', $repairOrder)
            ->withFragment('concern-'.$concern->id)
            ->with('status', $label.' updated for '.$concern->summary.'.');
    }

    private function applyPosture(RepairOrderConcern $concern, string $posture): void
    {
        if ($posture === RecommendationPresentation::INFORMATION_ONLY) {
            $concern->information_only = true;
            $concern->recommendation_priority = null;

            return;
        }

        $concern->recommendation_priority = RecommendationPriority::from($posture);
        $concern->information_only = false;
    }
}
