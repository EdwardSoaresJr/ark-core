<?php

namespace App\Ark\Operations\WorkTemplates;

use App\Ark\Dragon\Assist\DragonAssistProjection;
use App\Ark\Dragon\Assist\DragonAssistRequest;
use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Http\JsonResponse;

/**
 * Poll assist status for Saved Work modal (no second browser WebSocket stack required).
 */
final class HistoricalWorkRecallAssistStatusController
{
    public function __invoke(RepairOrder $repairOrder, string $assistRequest): JsonResponse
    {
        $assist = DragonAssistRequest::query()
            ->where('public_id', $assistRequest)
            ->where('repair_order_id', $repairOrder->id)
            ->with('result')
            ->firstOrFail();

        return response()->json([
            'assist' => DragonAssistProjection::fromRequest($assist),
        ]);
    }
}
