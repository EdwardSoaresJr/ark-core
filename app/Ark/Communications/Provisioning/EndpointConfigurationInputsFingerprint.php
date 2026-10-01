<?php

namespace App\Ark\Communications\Provisioning;

use App\Ark\Communications\Provisioning\Builders\PolyProvisionBuilder;
use App\Ark\Operations\Communications\CommunicationDevice;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Operations\Telephony\TelephonyExtension;
use App\Ark\Platform\VoiceTransportConfiguration;

/**
 * Deterministic fingerprint of authority inputs for EndpointConfigurationProjection.
 */
final class EndpointConfigurationInputsFingerprint
{
    public static function for(CommunicationDevice $device, TelephonyExtension $extension, ?CommunicationDeviceModel $deviceModel): string
    {
        $payload = [
            'device_id' => $device->id,
            'mac_address' => $device->mac_address,
            'is_active' => (bool) $device->is_active,
            'workstation_id' => $device->workstation_id,
            'firmware_version' => $device->firmware_version,
            'communication_device_model_id' => $device->communication_device_model_id,
            'extension' => $extension->extension,
            'extension_secret' => $extension->secret,
            'extension_display_name' => $extension->display_name,
            'model_slug' => $deviceModel?->slug,
            'model_latest_firmware' => $deviceModel?->latest_firmware,
            'provisioning_host' => VoiceTransportConfiguration::resolveRegistrar(),
            'poly_cfg_serialization_version' => PolyProvisionBuilder::SERIALIZATION_VERSION,
            'shop_timezone' => ShopDisplayTimezone::resolve(),
        ];

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
