<?php

namespace App\Ark\Operations\Settings;

use App\Ark\Platform\PlatformConnectOrchestrator;
use App\Ark\Platform\Http\PlatformConnectController;
use App\Ark\Mail\ArkMailActivationClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ShopPlatformSettingsController
{
    private function redirectToArkCloud(): RedirectResponse
    {
        return redirect()->route('operations.settings.shop.edit', [
            'section' => 'ark-cloud',
        ]);
    }

    public function connect(Request $request, PlatformConnectOrchestrator $orchestrator): RedirectResponse
    {
        $data = $request->validate([
            'ark_mail_service_url' => ['nullable', 'url', 'max:255'],
        ]);

        try {
            $serviceUrl = filled($data['ark_mail_service_url'] ?? null)
                ? rtrim((string) $data['ark_mail_service_url'], '/')
                : null;
            $returnTo = route('operations.cloud.connecting', absolute: true);
            $started = $orchestrator->begin($serviceUrl, $returnTo);
        } catch (\Throwable $e) {
            return $this->redirectToArkCloud()->with('status', 'Could not start connecting: '.$e->getMessage());
        }

        return redirect()->away($started['connect_url']);
    }

    public function claim(Request $request, ArkMailActivationClient $activation): RedirectResponse
    {
        $data = $request->validate([
            'pairing_public_id' => ['nullable', 'uuid'],
        ]);

        try {
            $activation->claimPairing($data['pairing_public_id'] ?? null);
        } catch (\Throwable $e) {
            return $this->redirectToArkCloud()->with('status', 'Could not finish connecting: '.$e->getMessage());
        }

        return $this->redirectToArkCloud()->with('status', 'ARK Platform connected.');
    }

    public function disconnect(ArkMailActivationClient $activation): RedirectResponse
    {
        $activation->disconnect();

        return $this->redirectToArkCloud()->with('status', 'ARK Platform disconnected.');
    }
}
