<?php

namespace App\Ark\Communications\Provisioning;

use App\Ark\Operations\Communications\CommunicationDevice;
use App\Ark\Operations\Telephony\TelephonyExtension;

final class EndpointProvisionGate
{
    public function evaluate(CommunicationDevice $device): ?EndpointProvisionGateFailure
    {
        if (! $device->is_active) {
            return EndpointProvisionGateFailure::Inactive;
        }

        if ($device->workstation_id === null) {
            return EndpointProvisionGateFailure::UnassignedWorkstation;
        }

        $extension = $this->extensionForDevice($device);

        if (! $extension instanceof TelephonyExtension) {
            return EndpointProvisionGateFailure::MissingExtension;
        }

        if (! $extension->isEnabled()) {
            return EndpointProvisionGateFailure::ExtensionDisabled;
        }

        return null;
    }

    public function extensionForDevice(CommunicationDevice $device): ?TelephonyExtension
    {
        $device->loadMissing('workstation');

        if ($device->workstation_id === null) {
            return null;
        }

        return TelephonyExtension::primaryForWorkstation((int) $device->workstation_id);
    }
}
