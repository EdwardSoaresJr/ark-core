<?php

namespace App\Ark\Mobile\Http;

use App\Ark\Mobile\MobileRepairOrderProjection;
use App\Ark\Mobile\MobileStaffAccess;
use App\Ark\Operations\Documents\EstimateDocumentService;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Events\OperationalEventRecorder;
use App\Ark\Operations\RepairOrders\ConcernBillingPosture;
use App\Ark\Operations\RepairOrders\RecordsRepairOrderEstimateMutation;
use App\Ark\Operations\RepairOrders\RecommendationIntent;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderConcurrency;
use App\Ark\Operations\RepairOrders\RepairOrderFreeText;
use App\Ark\Operations\RepairOrders\ScopeEntryConceptLearner;
use App\Ark\Operations\RepairOrders\ScopeEntryKind;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class MobileConcernStoreController
{
    use RecordsRepairOrderEstimateMutation;

    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        MobileStaffAccess $access,
        EstimateDocumentService $documents,
        OperationalEventRecorder $events,
        RepairOrderConcurrency $concurrency,
        ScopeEntryConceptLearner $conceptLearner,
        MobileRepairOrderProjection $projection,
    ): JsonResponse {
        $user = $request->user();

        abort_unless($access->canViewRepairOrder($user, $repairOrder), 403);
        abort_unless($access->canSetConcernDisposition($user, $repairOrder), 403);

        $repairOrder->ensureOpenForEditing();
        $concurrency->guard($request, $repairOrder);

        $data = $request->validate([
            'customer_states' => ['required', 'string', 'max:2000'],
            'recommendation_intent' => ['nullable', Rule::enum(RecommendationIntent::class)],
        ]);

        $customerStates = RepairOrderFreeText::normalize($data['customer_states']);
        $entryKind = ScopeEntryKind::inferFromSummary($customerStates);

        $repairOrder->loadMissing('customer');

        $concern = $repairOrder->concerns()->create([
            'summary' => $customerStates,
            'customer_states' => $customerStates,
            'scope_entry_kind' => $entryKind->value,
            'position' => ((int) $repairOrder->concerns()->max('position')) + 1,
            'recommendation_intent' => filled($data['recommendation_intent'] ?? null)
                ? $data['recommendation_intent']
                : $entryKind->defaultRecommendationIntent()->value,
            'disposition' => RepairOrderConcernDisposition::Draft,
            'billing_posture' => ConcernBillingPosture::defaultForCustomer($repairOrder->customer),
        ]);

        $conceptLearner->record(
            $concern,
            $entryKind,
            $customerStates,
            $customerStates,
            null,
        );

        $documents->markDirtyForRepairOrder($repairOrder);

        $events->record(
            OperationalEventName::ConcernCreated,
            $repairOrder,
            actor: $user,
            payload: [
                'concern_id' => $concern->id,
                'scope_entry_kind' => $concern->entryKind()->value,
                'recommendation_intent' => $concern->recommendationIntent()->value,
                'position' => $concern->position,
            ],
        );

        $this->recordRepairOrderEstimateMutation($repairOrder, $user);

        $repairOrder->refresh()->loadMissing([
            'customer',
            'vehicle',
            'assignedTechnician:id,name',
            'concerns.lines',
            'inspection.items.measurements',
            'inspection.items.photos',
            'inspection.items.concern',
        ]);

        return response()->json([
            'concern' => $projection->forConcern($repairOrder, $concern, $user, $access),
            'repair_order' => $projection->forRepairOrder($repairOrder, $user),
            'message' => 'Concern added.',
        ], 201);
    }
}
