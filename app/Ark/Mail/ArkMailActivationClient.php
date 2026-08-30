<?php

namespace App\Ark\Mail;

use App\Ark\Install\InstallationIdentity;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Box-side pairing against ARK Cloud.
 *
 * Flow: startPairing → operator approves in Cloud portal → claimPairing.
 * Cloud owns entitlement authority. This client only stores the issued credential.
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
            $message = $response->json('message') ?? 'Could not start ARK Cloud pairing.';
            throw new \RuntimeException(is_string($message) ? $message : 'Could not start ARK Cloud pairing.');
        }

        ShopSettings::current()->persistTrusted([
            'ark_mail_service_url' => $base,
            'ark_mail_status' => 'pairing',
        ]);

        return [
            'pairing_code' => (string) $response->json('pairing_code'),
            'pairing_public_id' => (string) $response->json('public_id'),
            'expires_at' => (string) $response->json('expires_at'),
            'installation_uuid' => $installationUuid,
        ];
    }

    /**
     * After portal approval, claim the one-time Cloud-issued installation credential.
     *
     * @return array{status: string, shop_public_id: ?string}
     */
    public function claimPairing(string $pairingPublicId, ?string $serviceUrl = null): array
    {
        $base = $this->baseUrl($serviceUrl);
        $installationUuid = InstallationIdentity::uuid();

        $response = Http::acceptJson()->timeout(20)->post($base.'/api/v1/pairing/claim', [
            'pairing_public_id' => $pairingPublicId,
            'installation_uuid' => $installationUuid,
        ]);

        if (! $response->successful() || ! ($response->json('ok') ?? false)) {
            $message = $response->json('message') ?? 'Pairing claim failed.';
            throw new \RuntimeException(is_string($message) ? $message : 'Pairing claim failed.');
        }

        $credential = $response->json('credential');
        if (! is_string($credential) || $credential === '') {
            throw new \RuntimeException('ARK Cloud did not return an installation credential.');
        }

        $settings = ShopSettings::current();
        $replyTo = $settings->postmark_reply_to ?: $settings->email;

        $settings->persistTrusted([
            'ark_mail_service_url' => $base,
            'ark_mail_tenant_public_id' => $response->json('shop_public_id'),
            'ark_mail_from_email' => null,
            'ark_mail_credential' => $credential,
            'ark_mail_status' => 'connected',
            'ark_mail_connected_at' => now(),
            'postmark_reply_to' => filled($replyTo) ? strtolower((string) $replyTo) : $settings->postmark_reply_to,
        ]);

        Log::info('ark_cloud.paired', [
            'shop_public_id' => $response->json('shop_public_id'),
            'installation_uuid' => $installationUuid,
        ]);

        return [
            'status' => 'connected',
            'shop_public_id' => $response->json('shop_public_id'),
        ];
    }

    /**
     * Settings "Connect" — starts pairing and returns the code for Cloud portal approval.
     *
     * @return array{status: string, pairing_code: string, pairing_public_id: string, expires_at: string, message: string}
     */
    public function activate(?string $serviceUrl = null): array
    {
        if (! config('services.ark_mail.allow_activation', false) && app()->environment('production')) {
            throw new \RuntimeException('ARK Cloud pairing is not available yet for this installation.');
        }

        $started = $this->startPairing($serviceUrl);

        return [
            'status' => 'pairing',
            'pairing_code' => $started['pairing_code'],
            'pairing_public_id' => $started['pairing_public_id'],
            'expires_at' => $started['expires_at'],
            'message' => 'Approve this code in ARK Cloud, then finish connecting from this Box.',
        ];
    }

    public function disconnect(): void
    {
        ShopSettings::current()->persistTrusted([
            'ark_mail_credential' => null,
            'ark_mail_tenant_public_id' => null,
            'ark_mail_from_email' => null,
            'ark_mail_status' => null,
            'ark_mail_connected_at' => null,
        ]);
    }

    private function baseUrl(?string $serviceUrl = null): string
    {
        $settings = ShopSettings::current();
        $base = rtrim((string) ($serviceUrl ?: $settings->ark_mail_service_url ?: config('services.ark_mail.base_url')), '/');

        if ($base === '') {
            throw new \RuntimeException('ARK Cloud service URL is not configured.');
        }

        return $base;
    }
}
