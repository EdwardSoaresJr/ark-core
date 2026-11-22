<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Platform\SaasProviderConfigurationGate;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class CommunicationsPersonController
{
    public function __invoke(User $user, CommunicationsShopProjection $projection): View|RedirectResponse
    {
        if (! SaasProviderConfigurationGate::allowsPhoneDeviceProvisioning()) {
            return SaasProviderConfigurationGate::refusePhoneDeviceProvisioning();
        }

        return view('operations.shop.communications.person', [
            'context' => $projection->personContext($user),
        ]);
    }
}
