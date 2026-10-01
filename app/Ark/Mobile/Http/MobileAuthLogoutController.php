<?php

namespace App\Ark\Mobile\Http;

use App\Ark\Mobile\MobileDevice;
use App\Ark\Operations\Telephony\MobileVoice\MobileVoiceEndpointRegistrar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MobileAuthLogoutController
{
    public function __invoke(Request $request, MobileVoiceEndpointRegistrar $voiceEndpoints): JsonResponse
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();
        $deviceName = trim((string) ($token?->name ?? ''));

        if ($user !== null && $deviceName !== '') {
            $device = MobileDevice::query()
                ->where('user_id', $user->id)
                ->where('device_name', $deviceName)
                ->first();

            if ($device instanceof MobileDevice) {
                $voiceEndpoints->disableForDevice($device);
            } else {
                $voiceEndpoints->disableForUser($user);
            }
        } elseif ($user !== null) {
            $voiceEndpoints->disableForUser($user);
        }

        $token?->delete();

        return response()->json(['message' => 'Signed out.']);
    }
}
