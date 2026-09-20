<?php

namespace App\Ark\Mobile\Http;

use App\Ark\Mobile\MobileDeviceResolver;
use App\Ark\Mobile\MobileRepairOrderRouteId;
use App\Ark\Mobile\MobileStaffAccess;
use App\Ark\Operations\Telephony\MobileVoice\MobileVoiceSessionIssuer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class MobileTelephonyVoiceConnectController
{
    public function __invoke(
        Request $request,
        MobileStaffAccess $access,
        MobileDeviceResolver $devices,
        MobileVoiceSessionIssuer $issuer,
    ): JsonResponse {
        abort_unless($access->canAccessShopCommunications($request->user()), 403);

        $data = $request->validate([
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'phone' => ['nullable', 'string', 'max:32'],
            'repair_order_id' => ['nullable', 'integer'],
        ]);

        if (! filled($data['customer_id'] ?? null) && ! filled($data['phone'] ?? null)) {
            return response()->json([
                'message' => 'A customer or phone number is required.',
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

        $device = $devices->resolve($request);

        if ($device === null) {
            return response()->json([
                'message' => 'Register this device with ARK Mobile before using in-app voice.',
            ], 422);
        }

        try {
            $connect = $issuer->connect(
                $request->user(),
                $device,
                isset($data['customer_id']) ? (int) $data['customer_id'] : null,
                filled($data['phone'] ?? null) ? (string) $data['phone'] : null,
                $repairOrderId,
            );
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'connect' => $connect,
        ]);
    }
}
