<?php

namespace App\Ark\Communications\Provisioning;

use App\Ark\Operations\Communications\CommunicationDevice;
use App\Ark\Operations\Telephony\TelephonyExtension;
use Illuminate\Support\Facades\DB;

final class InvalidateEndpointConfigurationAction
{
    public function execute(CommunicationDevice $device): void
    {
        EndpointConfigurationProjection::query()
            ->where('communication_device_id', $device->id)
            ->whereNull('superseded_at')
            ->update(['superseded_at' => now()]);
    }

    public function executeForWorkstation(int $workstationId): void
    {
        $deviceIds = CommunicationDevice::query()
            ->where('workstation_id', $workstationId)
            ->pluck('id');

        if ($deviceIds->isEmpty()) {
            return;
        }

        EndpointConfigurationProjection::query()
            ->whereIn('communication_device_id', $deviceIds)
            ->whereNull('superseded_at')
            ->update(['superseded_at' => now()]);
    }
}
