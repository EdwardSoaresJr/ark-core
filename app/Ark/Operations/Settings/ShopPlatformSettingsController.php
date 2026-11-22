<?php

namespace App\Ark\Operations\Settings;

use App\Ark\Mail\ArkMailActivationClient;
use App\Ark\Platform\PlatformConnection;
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

    /**
     * @param  mixed  $orchestrator  Ignored. Kept so SaaS overlays that still pass PlatformConnectOrchestrator do not fatally arity-mismatch.
     */
    public function connect(Request $request, mixed $orchestrator = null): RedirectResponse
    {
        return SaasProviderConfigurationGate::refuseBoxPairing();
    }

    /**
     * @param  mixed  $activation  Ignored. Kept for SaaS overlay call sites that still pass ArkMailActivationClient.
     */
    public function claim(Request $request, mixed $activation = null): RedirectResponse
    {
        return SaasProviderConfigurationGate::refuseBoxPairing();
    }

    /**
     * Legacy cleanup only: clear leftover pairing rows from discontinued Box enrollment.
     * This is not product-boundary authority for hosted vs standalone.
     */
    public function disconnect(ArkMailActivationClient $activation): RedirectResponse
    {
        if (! PlatformConnection::current()->isConnected()) {
            return SaasProviderConfigurationGate::refuseBoxPairing();
        }

        $activation->disconnect();

        return $this->redirectToGeneral()->with('status', 'Legacy cloud association cleared.');
    }
}
