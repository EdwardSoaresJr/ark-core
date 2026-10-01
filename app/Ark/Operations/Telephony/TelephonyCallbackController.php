<?php

namespace App\Ark\Operations\Telephony;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class TelephonyCallbackController
{
    public function __invoke(Request $request, TelephonyCallbackInitiator $initiator, CallSessionQueue $queue): JsonResponse
    {
        $data = $request->validate([
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'phone' => ['nullable', 'string', 'max:32'],
            'repair_order_id' => ['nullable', 'integer', 'exists:repair_orders,id'],
            'call_session_id' => ['nullable', 'integer', 'exists:call_sessions,id'],
        ]);

        if (! filled($data['customer_id'] ?? null) && ! filled($data['phone'] ?? null)) {
            return response()->json([
                'message' => 'A customer or phone number is required for callback.',
            ], 422);
        }

        try {
            $result = $initiator->initiate(
                $request->user(),
                customerId: isset($data['customer_id']) ? (int) $data['customer_id'] : null,
                phone: filled($data['phone'] ?? null) ? (string) $data['phone'] : null,
                repairOrderId: isset($data['repair_order_id']) ? (int) $data['repair_order_id'] : null,
            );
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        if (isset($data['call_session_id'])) {
            $session = CallSession::query()->find((int) $data['call_session_id']);

            if ($session !== null) {
                $queue->markCallerHandled($session);
            }
        } else {
            $queue->markCustomerOrPhoneHandled(
                isset($data['customer_id']) ? (int) $data['customer_id'] : null,
                filled($data['phone'] ?? null) ? (string) $data['phone'] : null,
            );
        }

        return response()->json($result);
    }
}
