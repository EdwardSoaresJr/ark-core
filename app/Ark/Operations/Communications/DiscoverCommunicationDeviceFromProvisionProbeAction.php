<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Communications\Provisioning\CommunicationDeviceMacAddress;
use App\Ark\Communications\Provisioning\CommunicationDeviceModelResolver;
use App\Ark\Operations\Settings\ShopSettings;

/**
 * When a phone probes provisioning, record it — operators assign stations; never type MACs.
 */
final class DiscoverCommunicationDeviceFromProvisionProbeAction
{
    public function __construct(
        private readonly CommunicationDeviceModelResolver $modelResolver,
    ) {}

    public function execute(string $macAddress): ?CommunicationDevice
    {
        try {
            $normalized = CommunicationDeviceMacAddress::normalize($macAddress);
        } catch (\InvalidArgumentException) {
            return null;
        }

        if ($normalized === '' || $normalized === '000000000000') {
            return null;
        }

        $existing = CommunicationDevice::query()
            ->where('mac_address', $normalized)
            ->first();

        if ($existing instanceof CommunicationDevice) {
            return $existing;
        }

        $shopId = ShopSettings::reloadCurrent()->id;

        $device = CommunicationDevice::query()->create([
            'shop_settings_id' => $shopId,
            'name' => 'New device',
            'model' => 'VVX350',
            'mac_address' => $normalized,
            'provider' => CommunicationDeviceProvider::ShopPhone,
            'status' => CommunicationDeviceStatus::Discovered,
            'capabilities' => CommunicationDeviceCapability::deskPhoneDefaults(),
            'is_active' => true,
        ]);

        $deviceModel = $this->modelResolver->resolveForDevice($device);

        if ($deviceModel !== null) {
            $device->communication_device_model_id = $deviceModel->id;
            $device->name = $deviceModel->label;
            $device->save();
        }

        return $device->fresh(['deviceModel']);
    }
}
