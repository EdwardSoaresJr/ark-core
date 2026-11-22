<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Platform\SaasProviderConfigurationGate;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class CommunicationDeviceController
{
    public function __invoke(
        CommunicationDevice $communicationDevice,
        CommunicationDeviceWorkspaceProjection $projection,
    ): View|RedirectResponse {
        if (! SaasProviderConfigurationGate::allowsPhoneDeviceProvisioning()) {
            return SaasProviderConfigurationGate::refusePhoneDeviceProvisioning();
        }

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
