<?php

namespace App\Ark\Mobile\Http;

use App\Ark\Mobile\MobileRepairOrderRouteId;
use App\Ark\Mobile\MobileStaffAccess;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionQueue;
use App\Ark\Operations\Telephony\TelephonyCallbackInitiator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class MobileTelephonyCallbackController
{
    public function __invoke(
        Request $request,
        MobileStaffAccess $access,
        TelephonyCallbackInitiator $initiator,
        CallSessionQueue $queue,
    ): JsonResponse {
        abort_unless($access->canAccessShopCommunications($request->user()), 403);

        $data = $request->validate([
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'phone' => ['nullable', 'string', 'max:32'],
            'repair_order_id' => ['nullable', 'integer'],
            'call_session_id' => ['nullable', 'integer', 'exists:call_sessions,id'],
        ]);

        if (! filled($data['customer_id'] ?? null) && ! filled($data['phone'] ?? null)) {
            return response()->json([
                'message' => 'A customer or phone number is required for callback.',
            ], 422);
        }

        $repairOrderId = isset($data['repair_order_id'])
            ? MobileRepairOrderRouteId::resolveInternalId((int) $data['repair_order_id'])
            : null;

        if (isset($data['repair_order_id']) && $repairOrderId === null) {
            return response()->json([
                'message' => 'The selected repair order id is invalid.',
            ], 422);
        }

        try {
            $result = $initiator->initiate(
                $request->user(),
                customerId: isset($data['customer_id']) ? (int) $data['customer_id'] : null,
                phone: filled($data['phone'] ?? null) ? (string) $data['phone'] : null,
                repairOrderId: $repairOrderId,
                mobileChannel: true,
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
