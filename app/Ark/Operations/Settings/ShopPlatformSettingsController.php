<?php

namespace App\Ark\Operations\Settings;

use App\Ark\Mail\ArkMailActivationClient;
use App\Ark\Platform\SaasProviderConfigurationGate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ShopPlatformSettingsController
{
    private function redirectToGeneral(): RedirectResponse
    {
        return redirect()->route('operations.settings.shop.edit', [
            'section' => 'general',
        ]);
    }

    public function connect(Request $request): RedirectResponse
    {
        return SaasProviderConfigurationGate::refuseBoxPairing();
    }

    public function claim(Request $request): RedirectResponse
    {
        return SaasProviderConfigurationGate::refuseBoxPairing();
    }

    public function disconnect(ArkMailActivationClient $activation): RedirectResponse
    {
        if (! SaasProviderConfigurationGate::platformConnected()) {
            return SaasProviderConfigurationGate::refuseBoxPairing();
        }

        $activation->disconnect();

        return $this->redirectToGeneral()->with('status', 'ARK Platform disconnected.');
    }
}
