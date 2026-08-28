<?php

namespace App\Ark\Mobile;

use App\Ark\Operations\Telephony\MobileVoice\MobileVoiceEndpointRegistrar;
use App\Models\User;

final class RegisterMobileDeviceAction
{
    public function __construct(
        private readonly MobileVoiceEndpointRegistrar $voiceEndpointRegistrar,
    ) {}

    public function execute(
        User $user,
        string $deviceName,
        string $platform,
        ?string $appVersion = null,
        ?string $fcmToken = null,
        ?string $voipPushToken = null,
    ): MobileDevice {
        $attributes = [
            'platform' => $platform,
            'app_version' => $appVersion,
            'last_seen_at' => now(),
        ];

        if ($fcmToken !== null) {
            $attributes['fcm_token'] = $fcmToken !== '' ? $fcmToken : null;
        }

        if ($voipPushToken !== null) {
            $attributes['voip_push_token'] = $voipPushToken !== '' ? $voipPushToken : null;
        }

        $device = MobileDevice::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'device_name' => $deviceName,
            ],
            $attributes,
        );

        // Ensure a MobileApp endpoint row exists for later voice readiness, but do not
        // mark StaffCallPresence or voice_ready_at — generic device registration is not
        // evidence that Twilio Client can ring this identity.
        $this->voiceEndpointRegistrar->ensureForDevice($device);

        return $device;
    }
}
