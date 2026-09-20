<?php

namespace App\Ark\Operations\Messaging;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class SendAdvisorMessageActionController
{
    public function __invoke(
        Request $request,
        Customer $customer,
        MessageActionKey $messageAction,
        SendAdvisorMessageAction $send,
        ConversationDeliveryJsonResponse $response,
    ): JsonResponse {
        if (! in_array($messageAction, MessageActionKey::advisorOneTap(), true)) {
            return response()->json(['message' => 'Unknown message action.'], 404);
        }

        $data = $request->validate([
            'repair_order_id' => ['nullable', 'integer', 'exists:repair_orders,id'],
        ]);

        $repairOrder = null;

        if (isset($data['repair_order_id'])) {
            $repairOrder = RepairOrder::query()
                ->whereKey($data['repair_order_id'])
                ->where('customer_id', $customer->id)
                ->first();

            if ($repairOrder === null) {
                return response()->json(['message' => 'Repair order does not belong to this customer.'], 422);
            }
        }

        try {
            $result = $send->execute($customer, $request->user(), $messageAction, $repairOrder);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return $response->make([$result['message']], [
            'message_action' => $messageAction->value,
        ]);
    }
}
