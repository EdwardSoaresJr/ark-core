<?php

namespace App\Ark\Platform;

use App\Ark\Install\EssentialDeliverySecret;
use App\Ark\Install\InstallationIdentity;
use App\Ark\Install\InstallationState;
use App\Ark\Install\RecoveryOwnerIdentity;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Installation-scoped Essential Delivery — not ARK Email, not pairing.
 */
final class EssentialDeliveryClient
{
    public function registerAtInstall(?string $serviceUrl = null): void
    {
        if (! InstallationState::isInstalled()) {
            return;
        }

        $email = RecoveryOwnerIdentity::email();
        if ($email === null) {
            return;
        }

        $base = $this->baseUrl($serviceUrl);
        if ($base === null) {
            return;
        }

        $secret = EssentialDeliverySecret::getOrCreate();

        try {
            $response = Http::acceptJson()->timeout(20)->post($base.'/api/v1/essential/register', [
                'installation_uuid' => InstallationIdentity::uuid(),
                'recovery_owner_email' => $email,
                'box_label' => ShopSettings::current()->shop_name ?: config('app.name', 'ARK'),
                'bootstrap_secret' => $secret,
            ]);

            if (! $response->successful() || ($response->json('ok') ?? false) !== true) {
                Log::warning('essential_delivery.register_failed', [
                    'status' => $response->status(),
                    'reason' => $response->json('reason_code'),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('essential_delivery.register_unavailable', ['error' => $e->getMessage()]);
        }
    }

    public function ensureRecoveryOwnerOnCloud(?string $serviceUrl = null): void
    {
        $email = RecoveryOwnerIdentity::email();
        if ($email === null) {
            return;
        }

        $credential = $this->signingCredential();
        if ($credential === null) {
            return;
        }

        $base = $this->baseUrl($serviceUrl);
        if ($base === null) {
            return;
        }

        $path = '/api/v1/essential/recovery-owner';
        $body = json_encode(['recovery_owner_email' => $email], JSON_THROW_ON_ERROR);
        $headers = PlatformSignedRequest::headers('POST', $path, $body, $credential);

        try {
            Http::withHeaders($headers)
                ->timeout(12)
                ->withBody($body, 'application/json')
                ->post($base.$path);
        } catch (\Throwable $e) {
            Log::warning('essential_delivery.recovery_owner_sync_failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * @return array{ok: bool, reason?: string}
     */
    public function deliverPasswordRecoveryCode(string $challengePublicId, string $code, string $idempotencyKey): array
    {
        $credential = $this->signingCredential();
        if ($credential === null) {
            return ['ok' => false, 'reason' => 'essential_unconfigured'];
        }

        $base = $this->baseUrl(null);
        if ($base === null) {
            return ['ok' => false, 'reason' => 'cloud_unreachable'];
        }

        $this->ensureRecoveryOwnerOnCloud($base);

        $path = '/api/v1/essential/delivery/password-recovery';
        $body = json_encode([
            'challenge_public_id' => $challengePublicId,
            'code' => $code,
            'idempotency_key' => $idempotencyKey,
        ], JSON_THROW_ON_ERROR);
        $headers = PlatformSignedRequest::headers('POST', $path, $body, $credential);

        try {
            $response = Http::withHeaders($headers)
                ->timeout(20)
                ->withBody($body, 'application/json')
                ->post($base.$path);
        } catch (\Throwable $e) {
            Log::warning('essential_delivery.send_failed', ['error' => $e->getMessage()]);

            return ['ok' => false, 'reason' => 'cloud_unreachable'];
        }

        if ($response->successful() && ($response->json('ok') ?? false) === true) {
            return ['ok' => true];
        }

        return [
            'ok' => false,
            'reason' => (string) ($response->json('reason_code') ?? 'delivery_rejected'),
        ];
    }

    private function signingCredential(): ?string
    {
        $cloud = PlatformConnection::current();
        if ($cloud->isConnected() && filled($cloud->credential())) {
            return (string) $cloud->credential();
        }

        $secret = EssentialDeliverySecret::path();
        if (is_file($secret)) {
            $value = trim((string) file_get_contents($secret));

            return strlen($value) >= 32 ? $value : null;
        }

        return null;
    }

    private function baseUrl(?string $serviceUrl): ?string
    {
        $base = rtrim((string) (
            $serviceUrl
            ?: PlatformConnection::current()->baseUrl()
            ?: config('services.ark_cloud.base_url')
            ?: config('services.ark_mail.base_url')
        ), '/');

        return $base !== '' ? $base : null;
    }
}
