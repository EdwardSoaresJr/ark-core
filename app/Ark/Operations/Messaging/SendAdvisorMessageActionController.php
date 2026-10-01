<?php

namespace App\Ark\Operations\Messaging;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Platform\Communications\CommunicationMessageContext;
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

        if ($result['message'] === null) {
            return response()->json([
                'platform_authoritative' => true,
                'message_id' => null,
                'provider_message_sid' => $result['provider_message_sid'] ?? null,
                'platform_message' => [
                    'direction' => 'outbound',
                    'direction_label' => 'Sent',
                    'channel_label' => 'SMS',
                    'body' => MessageActionsSettings::body($messageAction),
                    'occurred_at_label' => 'Just now',
                    'public_id' => $result['provider_message_sid'] ?? null,
                    'context_label' => $repairOrder instanceof RepairOrder
                        ? CommunicationMessageContext::label(CommunicationMessageContext::forRepairOrder($repairOrder))
                        : '',
                ],
                'message' => 'Message sent.',
                'message_action' => $messageAction->value,
            ]);
        }

        return $response->make([$result['message']], [
            'message_action' => $messageAction->value,
        ]);
    }
}
