<?php

namespace App\Ark\Operations\Communications;

use Illuminate\Contracts\View\View;

class CommunicationDeviceController
{
    public function __invoke(
        CommunicationDevice $communicationDevice,
        CommunicationDeviceWorkspaceProjection $projection,
    ): View {
        if (! ShopCommunicationsSchema::isReady()) {
            return view('operations.shop.communications.setup-required', [
                'missing' => ShopCommunicationsSchema::missingRequirements(),
            ]);
        }

        return view('operations.shop.communications.device', [
            'workspace' => $projection->resolve($communicationDevice),
        ]);
    }
}
