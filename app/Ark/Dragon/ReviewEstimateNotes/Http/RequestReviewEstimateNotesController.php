<?php

namespace App\Ark\Dragon\ReviewEstimateNotes\Http;

use App\Ark\Dragon\Assist\DragonAssistProjection;
use App\Ark\Dragon\ReviewEstimateNotes\RequestReviewEstimateNotesAction;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

final class RequestReviewEstimateNotesController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        RequestReviewEstimateNotesAction $action,
    ): JsonResponse {
        $repairOrder->ensureOpenForEditing();

        $data = $request->validate([
            'concern_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $scopeConcern = null;
        if (! empty($data['concern_id'])) {
            $scopeConcern = RepairOrderConcern::query()
                ->where('repair_order_id', $repairOrder->id)
                ->whereKey((int) $data['concern_id'])
                ->firstOrFail();
        }

        try {
            $assist = $action->execute($repairOrder, $request->user(), $scopeConcern);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $assist->load('result');

        return response()->json([
            'assist' => DragonAssistProjection::fromRequest($assist),
            'provenance' => $scopeConcern !== null
                ? 'Dragon Review Estimate Notes · This concern · Nothing changes until you Apply a proposal'
                : 'Dragon Review Estimate Notes · Nothing changes until you Apply a proposal',
        ], 201);
    }
}
