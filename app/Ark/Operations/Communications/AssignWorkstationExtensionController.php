<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Communications\Telephony\AssignExtensionToWorkstationAction;
use App\Ark\Operations\Telephony\TelephonyExtension;
use App\Ark\Operations\Telephony\TelephonyExtensionDeviceType;
use App\Ark\Operations\Workstations\Workstation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class AssignWorkstationExtensionController
{
    public function __invoke(
        Request $request,
        Workstation $workstation,
        AssignExtensionToWorkstationAction $assignExtension,
    ): RedirectResponse {
        if (! ShopCommunicationsSchema::isReady()) {
            return redirect()
                ->route('operations.shop.communications')
                ->withErrors(['extension' => 'Voice database schema is not migrated yet. Run migrations on the server, then retry.']);
        }

        $workstation->loadMissing('devices');

        $data = $request->validate([
            'extension' => ['required', 'string', 'regex:/^\d{2,4}$/'],
            'display_name' => ['required', 'string', 'max:128'],
            'secret' => ['nullable', 'string', 'min:8', 'max:64'],
            'regenerate_secret' => ['nullable', 'boolean'],
            'communication_device_id' => [
                'nullable',
                'integer',
                Rule::exists('communication_devices', 'id')->where(
                    fn ($query) => $query->where('workstation_id', $workstation->id),
                ),
            ],
        ]);

        $device = null;

        if (filled($data['communication_device_id'] ?? null)) {
            $device = CommunicationDevice::query()
                ->whereKey($data['communication_device_id'])
                ->where('workstation_id', $workstation->id)
                ->firstOrFail();
        } else {
            $device = $workstation->devices()->orderBy('name')->first();
        }

        $existingExtension = TelephonyExtension::primaryForWorkstation((int) $workstation->id);
        $secret = $this->resolveSecret(
            $request,
            $existingExtension,
            $data['secret'] ?? null,
        );

        try {
            $assignExtension->execute(
                workstation: $workstation,
                extension: $data['extension'],
                displayName: $data['display_name'],
                secret: $secret,
                communicationDevice: $device,
                deviceType: TelephonyExtensionDeviceType::DeskPhone,
            );
        } catch (InvalidArgumentException $exception) {
            return redirect()
                ->route('operations.shop.communications', ['assign' => $workstation->id])
                ->withInput()
                ->withErrors(['extension' => $exception->getMessage()]);
        }

        $message = $existingExtension instanceof TelephonyExtension
            ? "{$workstation->name} is ready. The phone is updating its line."
            : "{$workstation->name} is ready. The phone will configure itself.";

        return redirect()
            ->route('operations.shop.communications')
            ->with('status', $message);
    }

    private function resolveSecret(
        Request $request,
        ?TelephonyExtension $existingExtension,
        ?string $providedSecret,
    ): ?string {
        if (filled($providedSecret)) {
            return trim((string) $providedSecret);
        }

        $shouldGenerate = $existingExtension === null || $request->boolean('regenerate_secret');

        if (! $shouldGenerate) {
            return null;
        }

        return Str::password(16, letters: true, numbers: true, symbols: false);
    }
}
