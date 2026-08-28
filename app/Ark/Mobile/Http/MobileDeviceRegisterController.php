<?php

namespace App\Ark\Mobile\Http;

use App\Ark\Mobile\Push\MobilePushSettings;
use App\Ark\Mobile\RegisterMobileDeviceAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class MobileDeviceRegisterController
{
    public function __invoke(Request $request, RegisterMobileDeviceAction $register): JsonResponse
    {
        $data = $request->validate([
            'device_name' => ['required', 'string', 'max:120'],
            'platform' => ['required', 'string', 'max:32', Rule::in(['ios', 'android', 'ipados', 'other'])],
            'app_version' => ['nullable', 'string', 'max:32'],
            'fcm_token' => ['nullable', 'string', 'max:512'],
            'voip_push_token' => ['nullable', 'string', 'max:512'],
        ]);

        $device = $register->execute(
            user: $request->user(),
            deviceName: $data['device_name'],
            platform: $data['platform'],
            appVersion: $data['app_version'] ?? null,
            fcmToken: array_key_exists('fcm_token', $data) ? ($data['fcm_token'] ?? '') : null,
            voipPushToken: array_key_exists('voip_push_token', $data) ? ($data['voip_push_token'] ?? '') : null,
        );

        return response()->json([
            'device' => [
                'id' => $device->id,
                'device_name' => $device->device_name,
                'platform' => $device->platform,
                'app_version' => $device->app_version,
                'push_registered' => filled($device->fcm_token),
                'last_seen_at' => $device->last_seen_at?->toIso8601String(),
            ],
            'push_enabled' => MobilePushSettings::current()->isOperational(),
        ]);
    }
}
