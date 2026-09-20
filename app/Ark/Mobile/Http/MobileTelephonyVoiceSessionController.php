<?php

namespace App\Ark\Mobile\Http;

use App\Ark\Mobile\MobileDeviceResolver;
use App\Ark\Mobile\MobileStaffAccess;
use App\Ark\Operations\Telephony\MobileVoice\MobileVoiceSessionIssuer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class MobileTelephonyVoiceSessionController
{
    public function __invoke(
        Request $request,
        MobileStaffAccess $access,
        MobileDeviceResolver $devices,
        MobileVoiceSessionIssuer $issuer,
    ): JsonResponse {
        abort_unless($access->canAccessShopCommunications($request->user()), 403);

        $device = $devices->resolve($request);

        if ($device === null) {
            return response()->json([
                'message' => 'Register this device with ARK Mobile before using in-app voice.',
            ], 422);
        }

        try {
            $session = $issuer->sessionFor($request->user(), $device);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'session' => $session,
        ]);
    }
}
