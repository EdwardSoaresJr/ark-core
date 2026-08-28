<?php

namespace App\Ark\Communications\Provisioning;

use App\Ark\Operations\Communications\CommunicationDevice;
use App\Ark\Operations\Communications\CommunicationDeviceStatus;
use Illuminate\Support\Facades\Schema;

final class ServeEndpointProvisionAction
{
    public function __construct(
        private readonly RegenerateEndpointConfigurationAction $regenerate,
    ) {}

    public function execute(string $macAddress): RegenerateEndpointConfigurationResult
    {
        if (! Schema::hasColumn('communication_devices', 'mac_address')) {
            throw new EndpointProvisionNotFoundException;
        }

        $normalized = CommunicationDeviceMacAddress::normalize($macAddress);

        $device = CommunicationDevice::query()
            ->where('mac_address', $normalized)
            ->first();

        if (! $device instanceof CommunicationDevice) {
            app(DiscoverCommunicationDeviceFromProvisionProbeAction::class)->execute($normalized);

            throw new EndpointProvisionNotFoundException;
        }

        if ($device->status === CommunicationDeviceStatus::Discovered) {
            throw new EndpointProvisionNotFoundException;
        }

        return $this->regenerate->execute($device);
    }
}
