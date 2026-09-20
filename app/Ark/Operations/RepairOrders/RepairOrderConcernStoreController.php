<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\RepairOrders\RecordsRepairOrderEstimateMutation;
use App\Ark\Operations\Documents\EstimateDocumentService;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Events\OperationalEventRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RepairOrderConcernStoreController
{
    use RecordsRepairOrderEstimateMutation;

    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        EstimateDocumentService $documents,
        OperationalEventRecorder $events,
        RepairOrderConcurrency $concurrency,
        ScopeEntryConceptLearner $conceptLearner,
        ScopeRepairActionSuggestionQuery $repairActionSuggestions,
    ): RedirectResponse {
        $repairOrder->ensureOpenForEditing();
        $concurrency->guard($request, $repairOrder);

        $idempotencyKey = WorksheetMutationIdempotency::keyFrom($request);
        $cached = WorksheetMutationIdempotency::recall($repairOrder, 'concern.store', $idempotencyKey);
        if (is_array($cached) && isset($cached['concern_id'])) {
            $existing = RepairOrderConcern::query()
                ->where('repair_order_id', $repairOrder->id)
                ->whereKey($cached['concern_id'])
                ->first();

            if ($existing !== null) {
                return redirect()
                    ->route('operations.repair-orders.show', $repairOrder)
                    ->withFragment('concern-'.$existing->id)
                    ->with('status', 'Saved')
                    ->with('worksheet_focus_concern_id', $existing->id);
            }
        }

        $data = $request->validate([
            'scope_entry_kind' => ['nullable', Rule::enum(ScopeEntryKind::class)],
            'scope_entry_concept_id' => ['nullable', 'integer', 'exists:scope_entry_concepts,id'],
            'summary' => ['required', 'string', 'max:2000'],
            'observed_summary' => ['nullable', 'string', 'max:2000'],
            'customer_states' => ['nullable', 'string'],
            'verified_findings' => ['nullable', 'string'],
            'dtcs_summary' => ['nullable', 'string'],
            'recommendation' => ['nullable', 'string'],
            'recommendation_intent' => ['nullable', Rule::enum(RecommendationIntent::class)],
            WorksheetMutationIdempotency::FIELD => ['nullable', 'string', 'max:80'],
        ]);

        $observedSummary = RepairOrderFreeText::normalize($data['observed_summary'] ?? $data['summary']);
        $selectedSummary = RepairOrderFreeText::normalize($data['summary']);
        $scopeSummary = ScopeEntrySummaryResolver::resolve($selectedSummary, $observedSummary);

        $entryKind = filled($data['scope_entry_kind'] ?? null)
            ? ScopeEntryKind::from($data['scope_entry_kind'])
            : ScopeEntryKind::inferFromSummary($scopeSummary);

        if ($entryKind === ScopeEntryKind::CustomerRequested && blank($data['customer_states'] ?? null)) {
            $data['customer_states'] = $observedSummary !== '' ? $observedSummary : $scopeSummary;
        }

        if ($entryKind === ScopeEntryKind::CustomerConcern && blank($data['customer_states'] ?? null) && $observedSummary !== '') {
            $data['customer_states'] = $observedSummary;
        }

        $data['scope_entry_kind'] = $entryKind->value;
        $data['summary'] = $scopeSummary;
        $data['position'] = ((int) $repairOrder->concerns()->max('position')) + 1;
        $data['recommendation_intent'] = filled($data['recommendation_intent'] ?? null)
            ? $data['recommendation_intent']
            : $entryKind->defaultRecommendationIntent()->value;

        unset($data['observed_summary'], $data['scope_entry_concept_id']);

        $repairOrder->loadMissing('customer');

        $concern = $repairOrder->concerns()->create([
            ...$data,
            'disposition' => RepairOrderConcernDisposition::Draft,
            'billing_posture' => ConcernBillingPosture::defaultForCustomer($repairOrder->customer),
        ]);

        $conceptLearner->record(
            $concern,
            $entryKind,
            $scopeSummary,
            $observedSummary,
            filled($request->input('scope_entry_concept_id')) ? (int) $request->input('scope_entry_concept_id') : null,
        );

        $documents->markDirtyForRepairOrder($repairOrder);

        $events->record(
            OperationalEventName::ConcernCreated,
            $repairOrder,
            actor: $request->user(),
            payload: [
                'concern_id' => $concern->id,
                'scope_entry_kind' => $concern->entryKind()->value,
                'recommendation_intent' => $concern->recommendationIntent()->value,
                'position' => $concern->position,
            ],
        );

        $this->recordRepairOrderEstimateMutation($repairOrder, $request->user());

        $concern->refresh();
        $suggestedActions = $repairActionSuggestions->forConcern($concern);

        WorksheetMutationIdempotency::remember($repairOrder, 'concern.store', $idempotencyKey, [
            'concern_id' => $concern->id,
        ]);

        return redirect()
            ->route('operations.repair-orders.show', $repairOrder)
            ->withFragment('concern-'.$concern->id)
            ->with('status', 'Saved')
            ->with('worksheet_focus_concern_id', $concern->id)
            ->with('worksheet_repair_action_suggestions', [
                'concern_id' => $concern->id,
                'titles' => $suggestedActions,
            ]);
    }
}
