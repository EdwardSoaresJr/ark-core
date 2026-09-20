<?php

namespace App\Ark\Dragon\ServiceAdvisor\Http;

use App\Ark\Dragon\ServiceAdvisor\DragonServiceAdvisorApplication;
use App\Ark\Dragon\ServiceAdvisor\RevertServiceAdvisorRewriteAction;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcurrency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class RevertServiceAdvisorRewriteController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        DragonServiceAdvisorApplication $application,
        RevertServiceAdvisorRewriteAction $action,
        RepairOrderConcurrency $concurrency,
    ): JsonResponse {
        abort_unless((int) $application->repair_order_id === (int) $repairOrder->id, 404);
        $concurrency->guard($request, $repairOrder);

        try {
            $reverted = $action->execute($repairOrder, $application, $request->user());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'application' => [
                'public_id' => $reverted->public_id,
                'field' => $reverted->field->value,
                'original_text' => $reverted->original_text,
                'reverted_at' => $reverted->reverted_at?->toIso8601String(),
                'can_revert' => false,
            ],
            'status' => 'Reverted Dragon rewrite.',
        ]);
    }
}
