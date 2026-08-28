<?php

namespace App\Ark\Communications\Telephony;

use App\Ark\Operations\Communications\CommunicationDevice;
use App\Ark\Operations\Communications\ShopCommunicationsSchema;
use App\Ark\Operations\Communications\SuggestedWorkstationExtension;
use App\Ark\Operations\Telephony\TelephonyExtension;
use App\Ark\Operations\Telephony\TelephonyExtensionDeviceType;
use App\Ark\Operations\Workstations\Workstation;
use Illuminate\Support\Str;

/**
 * Operator path: extension identity is automatic when a workstation exists.
 * Numbers and SIP secrets stay in authority — not on the Voice setup surface.
 */
final class EnsureWorkstationTelephonyExtensionAction
{
    public function __construct(
        private readonly AssignExtensionToWorkstationAction $assignExtension,
    ) {}

    public function execute(
        Workstation $workstation,
        ?CommunicationDevice $communicationDevice = null,
    ): ?TelephonyExtension {
        if (! ShopCommunicationsSchema::isReady()) {
            return null;
        }

        $existing = TelephonyExtension::primaryForWorkstation((int) $workstation->id);

        if ($existing instanceof TelephonyExtension) {
            if ($communicationDevice instanceof CommunicationDevice
                && $existing->communication_device_id !== $communicationDevice->id) {
                return $this->assignExtension->execute(
                    workstation: $workstation,
                    extension: (string) $existing->extension,
                    displayName: (string) $existing->display_name,
                    secret: null,
                    communicationDevice: $communicationDevice,
                );
            }

            return $existing;
        }

        return $this->assignExtension->execute(
            workstation: $workstation,
            extension: SuggestedWorkstationExtension::next(),
            displayName: $workstation->name,
            secret: Str::password(16, letters: true, numbers: true, symbols: false),
            communicationDevice: $communicationDevice,
            deviceType: TelephonyExtensionDeviceType::DeskPhone,
        );
    }
}
