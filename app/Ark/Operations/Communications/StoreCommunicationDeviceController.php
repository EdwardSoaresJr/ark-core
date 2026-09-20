<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Communications\Provisioning\CommunicationDeviceMacAddress;
use App\Ark\Communications\Provisioning\CommunicationDeviceModelResolver;
use App\Ark\Communications\Telephony\EnsureWorkstationTelephonyExtensionAction;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Workstations\Workstation;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class StoreCommunicationDeviceController
{
    public function __invoke(
        Request $request,
        CommunicationDeviceModelResolver $modelResolver,
        EnsureWorkstationTelephonyExtensionAction $ensureExtension,
    ): RedirectResponse {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:128'],
            'model' => ['nullable', 'string', 'max:64'],
            'mac_address' => ['required', 'string', 'max:17'],
            'workstation_id' => ['nullable', 'integer', Rule::exists('workstations', 'id')],
            'assigned_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'provider' => ['required', Rule::enum(CommunicationDeviceProvider::class)],
        ]);

        try {
            $macAddress = CommunicationDeviceMacAddress::normalize((string) $data['mac_address']);
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages([
                'mac_address' => 'Enter the 12-character MAC from the phone label (for example 48:25:67:30:75:7F).',
            ]);
        }

        $shopId = ShopSettings::reloadCurrent()->id;

        if (CommunicationDevice::query()
            ->where('shop_settings_id', $shopId)
            ->where('mac_address', $macAddress)
            ->exists()) {
            throw ValidationException::withMessages([
                'mac_address' => 'This MAC address is already registered to another phone.',
            ]);
        }

        $modelLabel = filled($data['model'] ?? null) ? trim((string) $data['model']) : null;

        try {
            $device = DB::transaction(function () use (
                $data,
                $macAddress,
                $modelLabel,
                $modelResolver,
                $ensureExtension,
                $shopId,
            ): CommunicationDevice {
                $device = CommunicationDevice::query()->create([
                    'shop_settings_id' => $shopId,
                    'workstation_id' => $data['workstation_id'] ?? null,
                    'name' => trim($data['name']),
                    'model' => $modelLabel,
                    'mac_address' => $macAddress,
                    'assigned_user_id' => $data['assigned_user_id'] ?? null,
                    'provider' => $data['provider'],
                    'capabilities' => CommunicationDeviceCapability::deskPhoneDefaults(),
                    'status' => CommunicationDeviceStatus::WaitingForRegistration,
                    'is_active' => true,
                ]);

                $deviceModel = $modelResolver->resolveForDevice($device);

                if ($deviceModel !== null) {
                    $device->communication_device_model_id = $deviceModel->id;
                    $device->save();
                }

                if ($device->workstation_id !== null) {
                    $workstation = Workstation::query()->whereKey($device->workstation_id)->first();

                    if ($workstation instanceof Workstation) {
                        $ensureExtension->execute($workstation, $device->fresh());
                    }
                }

                return $device->fresh();
            });
        } catch (InvalidArgumentException $exception) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['device' => $exception->getMessage()]);
        } catch (UniqueConstraintViolationException) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['mac_address' => 'This MAC address is already registered to another phone.']);
        }

        if ($device->workstation_id !== null) {
            $workstationName = Workstation::query()->whereKey($device->workstation_id)->value('name');

            return redirect()
                ->route('operations.shop.devices.show', $device)
                ->with('status', $device->name.' added to '.($workstationName ?? 'workstation').'.');
        }

        return redirect()
            ->route('operations.shop.communications')
            ->with('status', $device->name.' added. Attach it to a workstation when ready.');
    }
}
