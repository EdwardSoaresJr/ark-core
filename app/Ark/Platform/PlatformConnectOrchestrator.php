<?php

namespace App\Ark\Platform;

use App\Ark\Install\InstallationIdentity;
use App\Ark\Mail\ArkMailActivationClient;
use App\Ark\Platform\Cloud\CloudUrls;
use Illuminate\Support\Facades\Http;

/**
 * One Core→Cloud connection journey for setup and Settings.
 * Wraps existing pairing start/claim; does not invent a second authority.
 */
final class PlatformConnectOrchestrator
{
    public function __construct(
        private readonly ArkMailActivationClient $activation,
    ) {}

    /**
     * @return array{pairing_public_id: string, pairing_code: string, connect_url: string}
     */
    public function begin(?string $serviceUrl = null, ?string $returnUrl = null): array
    {
        $started = $this->activation->activate($serviceUrl);
        $publicId = (string) ($started['pairing_public_id'] ?? '');
        $code = (string) ($started['pairing_code'] ?? '');

        $connectUrl = CloudUrls::connect($publicId, $returnUrl);
        if ($connectUrl === null) {
            throw new \RuntimeException('ARK Platform URL is not configured.');
        }

        return [
            'pairing_public_id' => $publicId,
            'pairing_code' => $code,
            'connect_url' => $connectUrl,
        ];
    }

    /**
     * @return array{status: string, shop_public_id: ?string, connected: bool}
     */
    public function pollAndClaim(): array
    {
        $cloud = PlatformConnection::current();
        $cloud->clearExpiredPairing();

        if ($cloud->isConnected()) {
            return [
                'status' => 'connected',
                'shop_public_id' => $cloud->shopPublicId(),
                'connected' => true,
            ];
        }

        $pairingPublicId = $cloud->pairingPublicId();
        if (! filled($pairingPublicId)) {
            return [
                'status' => 'idle',
                'shop_public_id' => null,
                'connected' => false,
            ];
        }

        $base = rtrim((string) $cloud->baseUrl(), '/');
        $installationUuid = InstallationIdentity::uuid();

        $statusResponse = Http::acceptJson()->timeout(15)->get($base.'/api/v1/pairing/status', [
            'pairing_public_id' => $pairingPublicId,
            'installation_uuid' => $installationUuid,
        ]);

        if (! $statusResponse->successful() || ! ($statusResponse->json('ok') ?? false)) {
            return [
                'status' => 'error',
                'shop_public_id' => null,
                'connected' => false,
            ];
        }

        $status = (string) $statusResponse->json('status');

        if ($status === 'approved_awaiting_box_claim') {
            $this->activation->claimPairing($pairingPublicId, $base);

            return [
                'status' => 'connected',
                'shop_public_id' => PlatformConnection::current()->shopPublicId(),
                'connected' => true,
            ];
        }

        if ($status === 'connected') {
            try {
                $this->activation->claimPairing($pairingPublicId, $base);
            } catch (\Throwable) {
                // Already claimed elsewhere; local state may still need refresh.
            }

            return [
                'status' => PlatformConnection::current()->isConnected() ? 'connected' : 'pending',
                'shop_public_id' => PlatformConnection::current()->shopPublicId(),
                'connected' => PlatformConnection::current()->isConnected(),
            ];
        }

        return [
            'status' => $status,
            'shop_public_id' => null,
            'connected' => false,
        ];
    }
}
