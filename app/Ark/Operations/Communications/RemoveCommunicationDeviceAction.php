<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Communications\Provisioning\EndpointConfigurationProjection;
use App\Ark\Operations\Telephony\TelephonyExtension;
use Illuminate\Support\Facades\DB;

/**
 * Remove a desk phone from the shop. Workstation extension identity is preserved for re-provisioning.
 */
final class RemoveCommunicationDeviceAction
{
    public function execute(CommunicationDevice $device): void
    {
        DB::transaction(function () use ($device): void {
            TelephonyExtension::query()
                ->where('communication_device_id', $device->id)
                ->update(['communication_device_id' => null]);

            EndpointConfigurationProjection::query()
                ->where('communication_device_id', $device->id)
                ->delete();

            $device->delete();
        });
    }
}
