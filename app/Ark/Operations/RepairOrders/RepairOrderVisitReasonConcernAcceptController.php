<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Documents\EstimateDocumentService;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Events\OperationalEventRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Accept one or more proposed concerns from visit_reason. Never mutates visit_reason.
 */
final class RepairOrderVisitReasonConcernAcceptController
{
    use RecordsRepairOrderEstimateMutation;

    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        EstimateDocumentService $documents,
        OperationalEventRecorder $events,
        RepairOrderConcurrency $concurrency,
        ScopeEntryConceptLearner $conceptLearner,
    ): RedirectResponse {
        $repairOrder->ensureOpenForEditing();
        $concurrency->guard($request, $repairOrder);

        $data = $request->validate([
            'proposals' => ['required', 'array', 'min:1', 'max:8'],
            'proposals.*.summary' => ['required', 'string', 'max:255'],
            'proposals.*.scope_entry_kind' => ['nullable', Rule::enum(ScopeEntryKind::class)],
        ]);

        $repairOrder->loadMissing('customer');
        $position = ((int) $repairOrder->concerns()->max('position'));
        $createdIds = [];

        foreach ($data['proposals'] as $proposal) {
            $summary = RepairOrderFreeText::normalize($proposal['summary']);

            if ($summary === '') {
                continue;
            }

            $entryKind = filled($proposal['scope_entry_kind'] ?? null)
                ? ScopeEntryKind::from($proposal['scope_entry_kind'])
                : ScopeEntryKind::CustomerConcern;

            $position++;

            $concern = $repairOrder->concerns()->create([
                'summary' => $summary,
                'customer_states' => null,
                'disposition' => RepairOrderConcernDisposition::Draft,
                'recommendation_intent' => $entryKind->defaultRecommendationIntent(),
                'billing_posture' => ConcernBillingPosture::defaultForCustomer($repairOrder->customer),
                'scope_entry_kind' => $entryKind,
                'position' => $position,
            ]);

            $conceptLearner->record($concern, $entryKind, $summary, $summary, null);

            $events->record(
                OperationalEventName::ConcernCreated,
                $repairOrder,
                actor: $request->user(),
                payload: [
                    'concern_id' => $concern->id,
                    'scope_entry_kind' => $entryKind->value,
                    'from_visit_reason_proposal' => true,
                    'position' => $concern->position,
                ],
            );

            $createdIds[] = $concern->id;
        }

        if ($createdIds !== []) {
            $documents->markDirtyForRepairOrder($repairOrder);
            $this->recordRepairOrderEstimateMutation($repairOrder, $request->user());
        }

        $request->session()->forget($this->dismissKey($repairOrder));

        $count = count($createdIds);

        return redirect()
            ->route('operations.repair-orders.show', $repairOrder)
            ->withFragment($count === 1 ? 'concern-'.$createdIds[0] : 'estimate-lines')
            ->with('status', $count === 1
                ? 'Concern added from visit reason.'
                : $count.' concerns added from visit reason.');
    }

    public static function dismissKey(RepairOrder $repairOrder): string
    {
        return 'visit_reason_concern_proposals_dismissed.'.$repairOrder->id;
    }
}
