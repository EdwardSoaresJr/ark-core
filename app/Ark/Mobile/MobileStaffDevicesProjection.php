<?php

namespace App\Ark\Mobile;

use App\Ark\Operations\Telephony\MobileVoice\MobileVoiceEndpointRegistrar;
use App\Ark\Operations\Telephony\MobileVoice\MobileVoiceRegistrationProjection;
use App\Ark\Operations\Telephony\TelephonyExtension;
use App\Ark\Operations\Telephony\TelephonyExtensionDeviceType;

/**
 * Read-only staff mobile devices for Settings → Communications → Mobile.
 */
final class MobileStaffDevicesProjection
{
    public function __construct(
        private readonly MobileVoiceEndpointRegistrar $voiceEndpoints,
        private readonly MobileVoiceRegistrationProjection $voiceRegistration,
    ) {}

    public static function forCurrentShop(): self
    {
        return new self(
            app(MobileVoiceEndpointRegistrar::class),
            app(MobileVoiceRegistrationProjection::class),
        );
    }

    /**
     * @return list<MobileStaffDeviceRow>
     */
    public function rows(): array
    {
        $liveCutoff = now()->subMinutes(MobileVoiceEndpointRegistrar::COVERAGE_PRESENCE_MINUTES);

        $extensionsByDeviceId = TelephonyExtension::query()
            ->where('device_type', TelephonyExtensionDeviceType::MobileApp)
            ->get()
            ->keyBy('mobile_device_id');

        return MobileDevice::query()
            ->with('user:id,name')
            ->orderByDesc('last_seen_at')
            ->orderBy('device_name')
            ->get()
            ->map(function (MobileDevice $device) use ($extensionsByDeviceId, $liveCutoff): MobileStaffDeviceRow {
                /** @var TelephonyExtension|null $extension */
                $extension = $extensionsByDeviceId->get($device->id);
                $endpoint = $this->voiceEndpoints->resolveForDevice($device);
                $voiceLive = $device->last_seen_at !== null
                    && $device->last_seen_at->gte($liveCutoff)
                    && $endpoint !== null;

                return new MobileStaffDeviceRow(
                    deviceId: (int) $device->id,
                    advisorName: (string) ($device->user?->name ?? 'Unknown'),
                    deviceName: $device->device_name,
                    platform: $device->platform,
                    appVersion: filled($device->app_version) ? (string) $device->app_version : null,
                    extension: $extension?->extension,
                    voiceEnabled: $extension !== null && $extension->isEnabled(),
                    voiceLive: $voiceLive,
                    pushTokenRegistered: filled($device->fcm_token),
                    lastSeenLabel: $device->last_seen_at?->diffForHumans(),
                );
            })
            ->all();
    }

    public function arkVoiceConfigured(): bool
    {
        return false;
    }
}
