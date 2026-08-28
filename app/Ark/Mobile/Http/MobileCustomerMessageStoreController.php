<?php

namespace App\Ark\Mobile\Http;

use App\Ark\Mobile\MobileStaffAccess;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Messaging\SendOutboundMessageAction;
use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Advisor-initiated SMS when no conversation thread exists yet — write path only.
 * Reuses SendOutboundMessageAction; ConversationRecorder creates thread authority.
 */
final class MobileCustomerMessageStoreController
{
    public function __invoke(
        Request $request,
        Customer $customer,
        MobileStaffAccess $access,
        SendOutboundMessageAction $sender,
    ): JsonResponse {
        abort_unless($access->canViewCustomer($request->user()), 403);
        abort_unless($access->canReplyToCustomer($request->user()), 403);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:1600'],
            'repair_order_id' => ['nullable', 'integer'],
        ]);

        $repairOrder = null;

        if (filled($data['repair_order_id'] ?? null)) {
            $repairOrder = RepairOrder::query()
                ->where('repair_order_id', $data['repair_order_id'])
                ->where('customer_id', $customer->id)
                ->first();

            abort_if($repairOrder === null, 422, 'Repair order does not belong to this customer.');
        }

        try {
            $result = $sender->execute(
                customer: $customer,
                actor: $request->user(),
                body: $data['body'],
                repairOrder: $repairOrder,
            );
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'conversation_id' => $result['message']->conversation_id,
            'message_id' => $result['message']->id,
        ], 201);
    }
}
