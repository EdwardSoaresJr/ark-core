<?php

namespace App\Ark\Communications\Telephony;

use App\Ark\Communications\Provisioning\InvalidateEndpointConfigurationAction;
use App\Ark\Operations\Communications\CommunicationDevice;
use App\Ark\Operations\Telephony\TelephonyExtension;
use App\Ark\Operations\Telephony\TelephonyExtensionDeviceType;
use App\Ark\Operations\Workstations\Workstation;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Telephony authority: assign business identity (extension) to a persistent workstation.
 *
 * Provisioning never allocates extensions — only this action (or equivalent POST paths) may.
 */
final class AssignExtensionToWorkstationAction
{
    public function __construct(
        private readonly InvalidateEndpointConfigurationAction $invalidateProjection,
    ) {}

    public function execute(
        Workstation $workstation,
        string $extension,
        string $displayName,
        ?string $secret = null,
        ?CommunicationDevice $communicationDevice = null,
        TelephonyExtensionDeviceType $deviceType = TelephonyExtensionDeviceType::DeskPhone,
    ): TelephonyExtension {
        $extensionNumber = trim($extension);
        $label = trim($displayName);

        if ($extensionNumber === '' || $label === '') {
            throw new InvalidArgumentException('Extension number and display name are required.');
        }

        if ($communicationDevice !== null) {
            $this->assertDeviceMatchesWorkstation($communicationDevice, $workstation);
        }

        return DB::transaction(function () use (
            $workstation,
            $extensionNumber,
            $label,
            $secret,
            $communicationDevice,
            $deviceType,
        ): TelephonyExtension {
            $existingForWorkstation = TelephonyExtension::primaryForWorkstation((int) $workstation->id);

            $conflicting = TelephonyExtension::queryForExtensionNumber($extensionNumber)
                ->when(
                    $existingForWorkstation instanceof TelephonyExtension,
                    fn ($query) => $query->where('id', '!=', $existingForWorkstation->id),
                )
                ->first();

            if ($conflicting instanceof TelephonyExtension) {
                if ($conflicting->workstation_id !== null) {
                    throw new InvalidArgumentException("Extension {$extensionNumber} is already assigned elsewhere.");
                }

                $telephonyExtension = $conflicting;
            } elseif ($existingForWorkstation instanceof TelephonyExtension) {
                $telephonyExtension = $existingForWorkstation;
            } else {
                $telephonyExtension = new TelephonyExtension([
                    'enabled' => true,
                    'device_type' => $deviceType,
                ]);
            }

            $telephonyExtension->fill([
                'extension' => $extensionNumber,
                'display_name' => $label,
                'workstation_id' => $workstation->id,
                'user_id' => null,
                'device_type' => $deviceType,
                'communication_device_id' => $communicationDevice?->id,
            ]);

            if ($secret !== null) {
                $telephonyExtension->secret = trim($secret) !== '' ? trim($secret) : null;
            }

            $telephonyExtension->save();

            $this->invalidateProjection->executeForWorkstation($workstation->id);

            if ($communicationDevice instanceof CommunicationDevice) {
                $communicationDevice->provider_identifier = $extensionNumber;
                $communicationDevice->save();
            }

            return $telephonyExtension->fresh(['workstation', 'communicationDevice']);
        });
    }

    private function assertDeviceMatchesWorkstation(CommunicationDevice $device, Workstation $workstation): void
    {
        if ($device->workstation_id === null) {
            throw new InvalidArgumentException('Communication device must be assigned to a workstation first.');
        }

        if ((int) $device->workstation_id !== (int) $workstation->id) {
            throw new RuntimeException('Communication device workstation does not match the target workstation.');
        }
    }
}
