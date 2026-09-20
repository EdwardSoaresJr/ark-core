<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Communications\Provisioning\EndpointProvisionGate;
use App\Ark\Communications\Provisioning\RegenerateEndpointConfigurationAction;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class GenerateCommunicationDeviceConfigAction
{
    public function __construct(
        private readonly RegenerateEndpointConfigurationAction $regenerate,
        private readonly EndpointProvisionGate $gate,
    ) {}

    public function execute(CommunicationDevice $device): CommunicationDevice
    {
        if ($device->workstation_id === null) {
            throw new RuntimeException('Attach this device to a workstation before generating config.');
        }

        $result = $this->regenerate->execute($device, force: true);

        Storage::disk('local')->put(
            $device->provisionConfigPath(),
            (string) $result->projection->serialized_config,
        );

        $extension = $this->gate->extensionForDevice($device);

        if ($extension !== null) {
            $device->provider_identifier = $extension->extension;
        }

        $device->status = CommunicationDeviceStatus::Provisioning;
        $device->save();

        return $device->fresh(['assignedUser', 'workstation']);
    }
}
