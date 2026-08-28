<?php

namespace App\Ark\Operations\Communications;

use Illuminate\Http\RedirectResponse;

class DestroyCommunicationDeviceController
{
    public function __invoke(
        CommunicationDevice $communicationDevice,
        RemoveCommunicationDeviceAction $remove,
    ): RedirectResponse {
        $name = $communicationDevice->name;

        $remove->execute($communicationDevice);

        return redirect()
            ->route('operations.shop.communications')
            ->with('status', $name.' removed. Add the phone again when you are ready.');
    }
}
