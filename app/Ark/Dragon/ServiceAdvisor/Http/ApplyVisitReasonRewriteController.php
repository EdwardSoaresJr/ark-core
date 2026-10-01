<?php

namespace App\Ark\Dragon\ServiceAdvisor\Http;

use App\Ark\Dragon\Assist\DragonAssistRequest;
use App\Ark\Dragon\ServiceAdvisor\ApplyVisitReasonRewriteAction;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcurrency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class ApplyVisitReasonRewriteController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        string $assistRequest,
        ApplyVisitReasonRewriteAction $action,
        RepairOrderConcurrency $concurrency,
    ): JsonResponse {
        $concurrency->guard($request, $repairOrder);

        $assist = DragonAssistRequest::query()
            ->where('public_id', $assistRequest)
            ->where('repair_order_id', $repairOrder->id)
            ->with('result')
            ->firstOrFail();

        $data = $request->validate([
            'edited_proposal' => ['nullable', 'string', 'max:8000'],
            RepairOrderConcurrency::FIELD => ['nullable', 'integer'],
        ]);

        try {
            $application = $action->execute(
                $repairOrder,
                $assist,
                $request->user(),
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
                'applied_text' => $application->applied_text,
                'applied_at' => $application->applied_at?->toIso8601String(),
                'can_revert' => $application->isApplied(),
            ],
            'status' => 'Applied Dragon rewrite.',
        ]);
    }
}
