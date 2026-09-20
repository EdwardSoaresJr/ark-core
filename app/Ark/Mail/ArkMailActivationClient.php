<?php

namespace App\Ark\Mail;

use App\Ark\Platform\PlatformConnection;
use App\Ark\Install\InstallationIdentity;
use App\Ark\Install\InstallationState;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Starts and finishes Cloud pairing from Settings → Email.
 *
 * Sequence: startPairing → approve in ARK Platform → claimPairing.
 */
final class ArkMailActivationClient
{
    /**
     * @return array{pairing_code: string, pairing_public_id: string, expires_at: string, installation_uuid: string}
     */
    public function startPairing(?string $serviceUrl = null): array
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
    public function claimPairing(?string $pairingPublicId = null, ?string $serviceUrl = null): array
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

        $settings = ShopSettings::current();
        $replyTo = $settings->postmark_reply_to ?: $settings->email;
        if (filled($replyTo)) {
            $settings->persistTrusted([
                'postmark_reply_to' => strtolower((string) $replyTo),
            ]);
        }

        // Essential Delivery is installation infrastructure after explicit Cloud connect — not at /setup.
        app(\App\Ark\Platform\EssentialDeliveryClient::class)->registerAtInstall($base);

        app(\App\Ark\Platform\PlatformStatusClient::class)->fetchAndPersistLocalMailProjection();
        app(ArkMailIdentityClient::class)->syncShopReplyTo();

        Log::info('ark_cloud.paired', [
            'shop_public_id' => $shopPublicId,
            'installation_uuid' => $installationUuid,
        ]);

        return [
            'status' => 'connected',
            'shop_public_id' => is_string($shopPublicId) ? $shopPublicId : null,
        ];
    }

    /**
     * @return array{status: string, pairing_code: string, pairing_public_id: string, expires_at: string, message: string}
     */
    public function activate(?string $serviceUrl = null): array
    {
        if (! InstallationState::isInstalled()) {
            throw new \RuntimeException('Finish installing ARK before connecting to ARK Platform.');
        }

        $started = $this->startPairing($serviceUrl);

        return [
            'status' => 'pairing',
            'pairing_code' => $started['pairing_code'],
            'pairing_public_id' => $started['pairing_public_id'],
            'expires_at' => $started['expires_at'],
            'message' => 'Approve this code in ARK Platform, then finish connecting here.',
        ];
    }

    public function disconnect(): void
    {
        PlatformConnection::current()->clear();
    }

    private function baseUrl(?string $serviceUrl = null): string
    {
        $base = rtrim((string) (
            $serviceUrl
            ?: PlatformConnection::current()->baseUrl()
            ?: config('services.ark_platform.base_url')
            ?: config('services.ark_cloud.base_url')
            ?: config('services.ark_mail.base_url')
        ), '/');

        if ($base === '') {
            throw new \RuntimeException('ARK Platform URL is not configured.');
        }

        return $base;
    }
}
