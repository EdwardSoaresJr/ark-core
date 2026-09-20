<?php

namespace App\Ark\Operations\Messaging;

use App\Ark\Operations\Communications\CancelScheduledOutboundMessagesAction;
use App\Ark\Operations\Communications\ScheduledOutboundEstimateProjection;
use App\Ark\Operations\Communications\ScheduledOutboundMessage;
use App\Ark\Operations\Communications\ScheduledOutboundMessageStatus;
use App\Ark\Operations\Communications\ScheduledOutboundMessageType;
use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CancelScheduledOutboundEstimateController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        CancelScheduledOutboundMessagesAction $cancel,
        ScheduledOutboundEstimateProjection $scheduleProjection,
    ): JsonResponse {
        $pending = ScheduledOutboundMessage::query()
            ->where('repair_order_id', $repairOrder->id)
            ->where('type', ScheduledOutboundMessageType::EstimateSend)
            ->where('status', ScheduledOutboundMessageStatus::Scheduled)
            ->orderByDesc('id')
            ->first();

        if ($pending === null) {
            return response()->json([
                'cancelled' => false,
                'schedule' => $scheduleProjection->forRepairOrder($repairOrder->id),
                'message' => 'No scheduled estimate send to cancel.',
            ]);
        }

        $cancel->cancel($pending, $request->user());

        return response()->json([
            'cancelled' => true,
            'schedule' => $scheduleProjection->forRepairOrder($repairOrder->id),
            'message' => 'Scheduled estimate send cancelled.',
        ]);
    }
}
