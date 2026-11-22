<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Platform\SaasProviderConfigurationGate;
use Illuminate\Contracts\View\View;

class CommunicationsShopController
{
    public function __invoke(CommunicationsShopProjection $projection): View
    {
        if (! ShopCommunicationsSchema::isReady()) {
            return view('operations.shop.communications.setup-required', [
                'missing' => ShopCommunicationsSchema::missingRequirements(),
            ]);
        }

        return view('operations.shop.communications.index', [
            'shop' => $projection->resolve(),
            'phoneProvisioningAvailable' => SaasProviderConfigurationGate::allowsPhoneDeviceProvisioning(),
        ]);
    }
}
