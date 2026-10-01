<?php

namespace App\Ark\Operations\Messaging;

use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class SendInspectionLinkController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        SendInspectionLinkAction $send,
        ConversationDeliveryJsonResponse $response,
    ): JsonResponse {
        try {
            $result = $send->execute($repairOrder, $request->user());
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return $response->make([$result['message']], array_filter([
            'inspection_url' => $result['url'],
            'token_reused' => $result['token_reused'],
        ], fn (mixed $value): bool => $value !== null));
    }
}
