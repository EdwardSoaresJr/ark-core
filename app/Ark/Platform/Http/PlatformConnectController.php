<?php

namespace App\Ark\Platform\Http;

use App\Ark\Platform\PlatformConnectOrchestrator;
use App\Ark\Platform\PlatformConnection;
use App\Ark\Install\InstallationState;
use App\Ark\Mail\ArkMailActivationClient;
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

        try {
            $returnTo = route('operations.cloud.connecting', absolute: true);
            $started = $orchestrator->begin(null, $returnTo);
        } catch (\Throwable $e) {
            return redirect()
                ->route('operations.settings.shop.edit', ['section' => 'ark-cloud'])
                ->with('status', 'Could not start connecting: '.$e->getMessage());
        }

        return redirect()->away($started['connect_url']);
    }

    public function start(Request $request, PlatformConnectOrchestrator $orchestrator): RedirectResponse
    {
        if (! InstallationState::isInstalled()) {
            abort(404);
        }

        $data = $request->validate([
            'ark_mail_service_url' => ['nullable', 'url', 'max:255'],
            'return_to' => ['nullable', 'string', 'max:255'],
        ]);

        $returnTo = $data['return_to'] ?? route('operations.cloud.connecting', absolute: true);
        if (! str_starts_with($returnTo, url('/'))) {
            $returnTo = route('operations.cloud.connecting', absolute: true);
        }

        try {
            $serviceUrl = filled($data['ark_mail_service_url'] ?? null)
                ? rtrim((string) $data['ark_mail_service_url'], '/')
                : null;
            $started = $orchestrator->begin($serviceUrl, $returnTo);
        } catch (\Throwable $e) {
            return redirect()
                ->route('operations.settings.shop.edit', ['section' => 'ark-cloud'])
                ->with('status', 'Could not start connecting: '.$e->getMessage());
        }

        return redirect()->away($started['connect_url']);
    }

    public function connecting(PlatformConnectOrchestrator $orchestrator): View|RedirectResponse
    {
        if (! InstallationState::isInstalled()) {
            abort(404);
        }

        $cloud = PlatformConnection::current();
        if ($cloud->isConnected()) {
            return redirect()
                ->route('operations.settings.shop.edit', ['section' => 'ark-cloud'])
                ->with('status', 'ARK Platform connected.');
        }

        return view('operations.cloud.connecting', [
            'pairingCode' => $cloud->pairingCode(),
            'pairingPublicId' => $cloud->pairingPublicId(),
            'cloudPairingUrl' => \App\Ark\Platform\Cloud\CloudUrls::pairing(),
        ]);
    }

    public function poll(PlatformConnectOrchestrator $orchestrator): JsonResponse
    {
        if (! InstallationState::isInstalled()) {
            abort(404);
        }

        try {
            $result = $orchestrator->pollAndClaim();
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'status' => 'error',
                'message' => $e->getMessage(),
                'connected' => false,
            ], 422);
        }

        return response()->json([
            'ok' => true,
            ...$result,
            'redirect' => $result['connected']
                ? route('operations.settings.shop.edit', ['section' => 'ark-cloud'])
                : null,
        ]);
    }

    public function startManual(Request $request, ArkMailActivationClient $activation): RedirectResponse
    {
        if (! InstallationState::isInstalled()) {
            abort(404);
        }

        $data = $request->validate([
            'ark_mail_service_url' => ['nullable', 'url', 'max:255'],
        ]);

        try {
            $serviceUrl = filled($data['ark_mail_service_url'] ?? null)
                ? rtrim((string) $data['ark_mail_service_url'], '/')
                : null;
            $started = $activation->activate($serviceUrl);
        } catch (\Throwable $e) {
            return redirect()
                ->route('operations.settings.shop.edit', ['section' => 'ark-cloud'])
                ->with('status', 'Could not start connecting: '.$e->getMessage());
        }

        $code = $started['pairing_code'] ?? '';

        return redirect()
            ->route('operations.settings.shop.edit', ['section' => 'ark-cloud'])
            ->with(
                'status',
                $code !== ''
                    ? "Pairing code {$code}. Approve it in ARK Platform, then finish connecting here."
                    : 'Approve the pairing code in ARK Platform, then finish connecting here.'
            );
    }
}
