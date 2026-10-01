<?php

namespace App\Ark\Operations\Communications;

use Illuminate\Http\RedirectResponse;
use RuntimeException;

class GenerateCommunicationDeviceConfigController
{
    public function __invoke(
        CommunicationDevice $communicationDevice,
        GenerateCommunicationDeviceConfigAction $generateConfig,
    ): RedirectResponse {
        try {
            $generateConfig->execute($communicationDevice);
        } catch (RuntimeException $exception) {
            return redirect()
                ->route('operations.shop.devices.show', $communicationDevice)
                ->withErrors(['device' => $exception->getMessage()]);
        }

        return redirect()
            ->route('operations.shop.devices.show', $communicationDevice)
            ->with('status', 'Config generated. Download it, load it on the phone, then reboot when ready.');
    }
}
