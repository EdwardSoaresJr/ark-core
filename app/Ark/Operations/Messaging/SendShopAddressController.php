<?php

namespace App\Ark\Operations\Messaging;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Back-compat route for Send Address — delegates to MessageActionKey::Address.
 */
class SendShopAddressController
{
    public function __invoke(
        Request $request,
        Customer $customer,
        SendAdvisorMessageAction $send,
        ConversationDeliveryJsonResponse $response,
    ): JsonResponse {
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
            $result = $send->execute($customer, $request->user(), MessageActionKey::Address, $repairOrder);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return $response->make([$result['message']]);
    }
}
