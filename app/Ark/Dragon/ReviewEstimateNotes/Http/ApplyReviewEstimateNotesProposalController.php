<?php

namespace App\Ark\Dragon\ReviewEstimateNotes\Http;

use App\Ark\Dragon\Assist\DragonAssistRequest;
use App\Ark\Dragon\ReviewEstimateNotes\ApplyReviewEstimateNotesProposalAction;
use App\Ark\Dragon\ServiceAdvisor\ServiceAdvisorField;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcurrency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

final class ApplyReviewEstimateNotesProposalController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        string $assistRequest,
        ApplyReviewEstimateNotesProposalAction $action,
        RepairOrderConcurrency $concurrency,
    ): JsonResponse {
        $concurrency->guard($request, $repairOrder);

        $assist = DragonAssistRequest::query()
            ->where('public_id', $assistRequest)
            ->where('repair_order_id', $repairOrder->id)
            ->with('result')
            ->firstOrFail();

        $data = $request->validate([
            'concern_id' => ['nullable', 'integer', 'min:1'],
            'line_id' => ['nullable', 'integer', 'min:1'],
            'field' => ['required', 'string', Rule::in(ServiceAdvisorField::values())],
            'edited_proposal' => ['nullable', 'string', 'max:8000'],
            RepairOrderConcurrency::FIELD => ['nullable', 'integer'],
        ]);

        $field = ServiceAdvisorField::from($data['field']);
        if ($field->isConcernNarrative() && empty($data['concern_id'])) {
            return response()->json(['message' => 'concern_id is required for this proposal.'], 422);
        }
        if ($field === ServiceAdvisorField::LineNote && empty($data['line_id'])) {
            return response()->json(['message' => 'line_id is required for this proposal.'], 422);
        }

        try {
            $application = $action->execute(
                $repairOrder,
                $assist,
                $field,
                $request->user(),
                isset($data['concern_id']) ? (int) $data['concern_id'] : null,
                isset($data['line_id']) ? (int) $data['line_id'] : null,
                $data['edited_proposal'] ?? null,
                isset($data[RepairOrderConcurrency::FIELD]) ? (int) $data[RepairOrderConcurrency::FIELD] : null,
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json([
            'application' => [
                'public_id' => $application->public_id,
                'field' => $application->field->value,
                'concern_id' => $application->concern_id,
                'repair_order_line_id' => $application->repair_order_line_id,
                'applied_text' => $application->applied_text,
                'applied_at' => $application->applied_at?->toIso8601String(),
                'can_revert' => $application->isApplied(),
            ],
            'status' => 'Applied Dragon proposal.',
        ]);
    }
}
