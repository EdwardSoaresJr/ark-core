<?php

namespace App\Ark\Platform\Http;

use App\Ark\Install\InstallationState;
use App\Ark\Mail\ArkMailActivationClient;
use App\Ark\Platform\PlatformConnectOrchestrator;
use App\Ark\Platform\SaasProviderConfigurationGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class PlatformConnectController
{
    public function startAfterSetup(Request $request, PlatformConnectOrchestrator $orchestrator): RedirectResponse
    {
        if (! InstallationState::isInstalled()) {
            abort(404);
        }

        session()->forget('install.connect_cloud_after_install');
        @unlink(\App\Ark\Install\InstallStorage::path('connect_cloud_after_install'));

        return SaasProviderConfigurationGate::refuseBoxPairing();
    }

    public function start(Request $request, PlatformConnectOrchestrator $orchestrator): RedirectResponse
    {
        if (! InstallationState::isInstalled()) {
            abort(404);
        }

        return SaasProviderConfigurationGate::refuseBoxPairing();
    }

    public function connecting(PlatformConnectOrchestrator $orchestrator): View|RedirectResponse
    {
        if (! InstallationState::isInstalled()) {
            abort(404);
        }

        return SaasProviderConfigurationGate::refuseBoxPairing();
    }

    public function poll(PlatformConnectOrchestrator $orchestrator): JsonResponse
    {
        if (! InstallationState::isInstalled()) {
            abort(404);
        }

        return response()->json([
            'ok' => false,
            'status' => 'refused',
            'message' => 'ARK Platform Box pairing is not configured from Core. Hosted shops are associated by ARK Shop / Landlord.',
            'connected' => false,
        ], 403);
    }

    public function startManual(Request $request, ArkMailActivationClient $activation): RedirectResponse
    {
        if (! InstallationState::isInstalled()) {
            abort(404);
        }

        return SaasProviderConfigurationGate::refuseBoxPairing();
    }
}
