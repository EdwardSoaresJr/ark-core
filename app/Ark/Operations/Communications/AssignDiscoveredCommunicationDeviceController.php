<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Communications\Provisioning\RegenerateEndpointConfigurationAction;
use App\Ark\Communications\Telephony\EnsureWorkstationTelephonyExtensionAction;
use App\Ark\Operations\Workstations\StoreWorkstationAction;
use App\Ark\Operations\Workstations\Workstation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class AssignDiscoveredCommunicationDeviceController
{
    public function __invoke(
        Request $request,
        CommunicationDevice $communicationDevice,
        EnsureWorkstationTelephonyExtensionAction $ensureExtension,
        RegenerateEndpointConfigurationAction $regenerate,
        StoreWorkstationAction $storeWorkstation,
    ): RedirectResponse {
        if ($communicationDevice->status !== CommunicationDeviceStatus::Discovered) {
            throw ValidationException::withMessages([
                'device' => 'This device is no longer waiting for station assignment.',
            ]);
        }

        $data = $request->validate([
            'workstation_id' => ['nullable', 'integer', Rule::exists('workstations', 'id')],
            'new_station_name' => ['nullable', 'string', 'max:128'],
        ]);

        $workstation = null;

        if (filled($data['new_station_name'] ?? null)) {
            $workstation = $storeWorkstation->execute(
                trim((string) $data['new_station_name']),
                null,
            );
        } elseif (filled($data['workstation_id'] ?? null)) {
            $workstation = Workstation::query()->findOrFail((int) $data['workstation_id']);
        }

        if (! $workstation instanceof Workstation) {
            throw ValidationException::withMessages([
                'workstation_id' => 'Choose a station or create a new one.',
            ]);
        }

        $communicationDevice->workstation_id = $workstation->id;
        $communicationDevice->name = $workstation->name.' · '.($communicationDevice->deviceModel?->label ?? $communicationDevice->model ?? 'Phone');
        $communicationDevice->status = CommunicationDeviceStatus::WaitingForRegistration;
        $communicationDevice->save();

        $ensureExtension->execute($workstation, $communicationDevice->fresh());
        $regenerate->execute($communicationDevice->fresh());

        return redirect()
            ->route('operations.shop.devices.show', $communicationDevice)
            ->with('status', $workstation->name.' is ready. The phone is configuring itself.');
    }
}
