<?php

namespace App\Ark\Operations\Communications;

/**
 * Transport truth: registration observed by provider → device posture.
 * Match by device identity (provider_identifier) only — never auto-create.
 */
final class UpdateCommunicationDeviceRegistrationAction
{
    public function execute(string $providerIdentifier, bool $registered): ?CommunicationDevice
    {
        $identifier = trim($providerIdentifier);

        if ($identifier === '') {
            return null;
        }

        $device = CommunicationDevice::query()
            ->where('provider', CommunicationDeviceProvider::ShopPhone)
            ->where('provider_identifier', $identifier)
            ->first();

        if (! $device instanceof CommunicationDevice) {
            return null;
        }

        if ($registered) {
            $previousStatus = $device->status;
            $device->last_registered_at = now();
            $device->status = match ($previousStatus) {
                CommunicationDeviceStatus::Provisioning => CommunicationDeviceStatus::Provisioned,
                CommunicationDeviceStatus::WaitingForRegistration,
                CommunicationDeviceStatus::Offline => CommunicationDeviceStatus::Connected,
                default => $previousStatus->isRegistered()
                    ? $previousStatus
                    : CommunicationDeviceStatus::Connected,
            };

            if ($previousStatus === CommunicationDeviceStatus::Provisioning) {
                $device->last_provisioned_at = now();
            }
        } else {
            $device->status = in_array($device->status, [
                CommunicationDeviceStatus::Connected,
                CommunicationDeviceStatus::Provisioned,
            ], true)
                ? CommunicationDeviceStatus::WaitingForRegistration
                : CommunicationDeviceStatus::Offline;
        }

        if (! $device->isDirty()) {
            return $device;
        }

        $device->save();

        return $device->fresh();
    }
}
