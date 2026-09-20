<?php

namespace App\Ark\Platform;

use App\Ark\Install\InstallationIdentity;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Core→Platform pairing: start → Platform approve → claim credential.
 */
final class PlatformPairingClient
{
    /**
     * @return array{pairing_code: string, pairing_public_id: string, expires_at: string, installation_uuid: string}
     */
    public function start(?string $serviceUrl = null): array
    {
        $base = $this->baseUrl($serviceUrl);
        $installationUuid = InstallationIdentity::uuid();

        $response = Http::acceptJson()->timeout(20)->post($base.'/api/v1/pairing/start', [
            'installation_uuid' => $installationUuid,
            'box_label' => ShopSettings::current()->shop_name ?: config('app.name', 'ARK'),
        ]);

        if (! $response->successful() || ! ($response->json('ok') ?? false)) {
            $message = $response->json('message') ?? 'Could not start pairing.';
            throw new \RuntimeException(is_string($message) ? $message : 'Could not start pairing.');
        }

        $pairingPublicId = (string) $response->json('public_id');
        $pairingCode = (string) $response->json('pairing_code');
        $expiresAt = (string) $response->json('expires_at');

        PlatformConnection::current()->beginPairing(
            $base,
            $pairingPublicId,
            $pairingCode,
            $expiresAt,
        );

        return [
            'pairing_code' => $pairingCode,
            'pairing_public_id' => $pairingPublicId,
            'expires_at' => $expiresAt,
            'installation_uuid' => $installationUuid,
        ];
    }

    /**
     * @return array{status: string, shop_public_id: ?string}
     */
    public function claim(?string $pairingPublicId = null, ?string $serviceUrl = null): array
    {
        $cloud = PlatformConnection::current();
        $cloud->clearExpiredPairing();

        $pairingPublicId = $pairingPublicId ?: $cloud->pairingPublicId();
        if (! filled($pairingPublicId)) {
            throw new \RuntimeException('No pairing in progress. Start connecting again.');
        }

        $base = $this->baseUrl($serviceUrl);
        $installationUuid = InstallationIdentity::uuid();

        $response = Http::acceptJson()->timeout(20)->post($base.'/api/v1/pairing/claim', [
            'pairing_public_id' => $pairingPublicId,
            'installation_uuid' => $installationUuid,
        ]);

        if (! $response->successful() || ! ($response->json('ok') ?? false)) {
            $message = $response->json('message') ?? 'Could not finish pairing.';
            throw new \RuntimeException(is_string($message) ? $message : 'Could not finish pairing.');
        }

        $credential = $response->json('credential');
        if (! is_string($credential) || $credential === '') {
            throw new \RuntimeException('ARK Platform did not return a connection credential.');
        }

        $shopPublicId = $response->json('shop_public_id');
        $cloud->completePairing(
            $base,
            $credential,
            is_string($shopPublicId) ? $shopPublicId : null,
        );

        Log::info('ark_platform.paired', [
            'shop_public_id' => $shopPublicId,
            'installation_uuid' => $installationUuid,
        ]);

        return [
            'status' => 'connected',
            'shop_public_id' => is_string($shopPublicId) ? $shopPublicId : null,
        ];
    }

    /**
     * Soft-fail status check — never throws for ordinary shop operation.
     *
     * @return array{ok: bool, connected: bool, status: ?string, error: ?string}
     */
    public function statusSoft(): array
    {
        $cloud = PlatformConnection::current();
        if (! $cloud->isConnected()) {
            return [
                'ok' => true,
                'connected' => false,
                'status' => $cloud->status(),
                'error' => null,
            ];
        }

        try {
            $path = '/api/v1/status';
            $credential = (string) $cloud->credential();
            $installationUuid = InstallationIdentity::uuid();
            $timestamp = (string) time();
            $nonce = \Illuminate\Support\Str::random(24);
            $signature = hash_hmac('sha256', implode("\n", [
                $timestamp,
                $nonce,
                'GET',
                $path,
                hash('sha256', ''),
            ]), $credential);

            $response = Http::acceptJson()
                ->timeout(5)
                ->withHeaders([
                    'X-Ark-Installation-Id' => $installationUuid,
                    'X-Ark-Timestamp' => $timestamp,
                    'X-Ark-Nonce' => $nonce,
                    'X-Ark-Signature' => $signature,
                ])
                ->get(rtrim($cloud->baseUrl(), '/').$path);

            if (! $response->successful()) {
                return [
                    'ok' => false,
                    'connected' => true,
                    'status' => 'unreachable',
                    'error' => 'Platform status HTTP '.$response->status(),
                ];
            }

            return [
                'ok' => (bool) ($response->json('ok') ?? true),
                'connected' => true,
                'status' => 'connected',
                'error' => null,
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'connected' => true,
                'status' => 'unreachable',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function baseUrl(?string $serviceUrl): string
    {
        $base = rtrim((string) (
            $serviceUrl
            ?: PlatformConnection::current()->baseUrl()
            ?: config('services.ark_platform.base_url')
        ), '/');

        if ($base === '') {
            throw new \RuntimeException('ARK Platform URL is not configured.');
        }

        return $base;
    }
}
