<?php

namespace App\Ark\Platform;

use App\Ark\Operations\Settings\ShopSettings;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

final class PlatformConnection
{
    public static function current(): self
    {
        return new self(ShopSettings::current());
    }

    public function __construct(
        private readonly ShopSettings $settings,
    ) {}

    public function isConnected(): bool
    {
        return $this->status() === 'connected'
            && filled($this->credential())
            && filled($this->baseUrl());
    }

    public function isPairing(): bool
    {
        $this->clearExpiredPairing();

        return $this->status() === 'pairing' && filled($this->pairingPublicId());
    }

    public function isSuspended(): bool
    {
        return $this->status() === 'suspended';
    }

    public function status(): ?string
    {
        if (filled($this->settings->platform_status ?? null)) {
            return (string) $this->settings->platform_status;
        }

        if (filled($this->settings->cloud_status)) {
            return (string) $this->settings->cloud_status;
        }

        return filled($this->settings->ark_mail_status)
            ? (string) $this->settings->ark_mail_status
            : null;
    }

    public function baseUrl(): string
    {
        return rtrim((string) (
            ($this->settings->platform_base_url ?? null)
            ?: $this->settings->cloud_base_url
            ?: $this->settings->ark_mail_service_url
            ?: config('services.ark_platform.base_url')
            ?: config('services.ark_cloud.base_url')
            ?: config('services.ark_mail.base_url')
        ), '/');
    }

    public function credential(): ?string
    {
        $value = ($this->settings->platform_credential ?? null)
            ?: $this->settings->cloud_credential
            ?: $this->settings->ark_mail_credential;

        return filled($value) ? (string) $value : null;
    }

    public function shopPublicId(): ?string
    {
        $value = ($this->settings->platform_shop_public_id ?? null)
            ?: $this->settings->cloud_shop_public_id
            ?: $this->settings->ark_mail_tenant_public_id;

        return filled($value) ? (string) $value : null;
    }

    public function pairingPublicId(): ?string
    {
        $value = ($this->settings->platform_pairing_public_id ?? null)
            ?: $this->settings->cloud_pairing_public_id;

        return filled($value) ? (string) $value : null;
    }

    public function pairingCode(): ?string
    {
        $value = ($this->settings->platform_pairing_code ?? null)
            ?: $this->settings->cloud_pairing_code;

        return filled($value) ? (string) $value : null;
    }

    public function pairingExpiresAt(): ?CarbonInterface
    {
        $value = ($this->settings->platform_pairing_expires_at ?? null)
            ?: $this->settings->cloud_pairing_expires_at;

        return $value instanceof CarbonInterface ? $value : null;
    }

    public function beginPairing(
        string $baseUrl,
        string $pairingPublicId,
        string $pairingCode,
        string $expiresAt,
    ): void {
        $baseUrl = rtrim($baseUrl, '/');

        $this->settings->persistTrusted([
            'cloud_base_url' => $baseUrl,
            'platform_base_url' => $baseUrl,
            'cloud_status' => 'pairing',
            'platform_status' => 'pairing',
            'cloud_pairing_public_id' => $pairingPublicId,
            'platform_pairing_public_id' => $pairingPublicId,
            'cloud_pairing_code' => strtoupper($pairingCode),
            'platform_pairing_code' => strtoupper($pairingCode),
            'cloud_pairing_expires_at' => Carbon::parse($expiresAt),
            'platform_pairing_expires_at' => Carbon::parse($expiresAt),
            'cloud_credential' => null,
            'platform_credential' => null,
            'cloud_shop_public_id' => null,
            'platform_shop_public_id' => null,
            'cloud_connected_at' => null,
            'platform_connected_at' => null,
            'ark_mail_service_url' => $baseUrl,
            'ark_mail_status' => 'pairing',
            'ark_mail_credential' => null,
            'ark_mail_tenant_public_id' => null,
            'ark_mail_connected_at' => null,
        ]);
    }

    public function completePairing(
        string $baseUrl,
        string $credential,
        ?string $shopPublicId,
    ): void {
        $baseUrl = rtrim($baseUrl, '/');

        $this->settings->persistTrusted([
            'cloud_base_url' => $baseUrl,
            'platform_base_url' => $baseUrl,
            'cloud_credential' => $credential,
            'platform_credential' => $credential,
            'cloud_shop_public_id' => $shopPublicId,
            'platform_shop_public_id' => $shopPublicId,
            'cloud_status' => 'connected',
            'platform_status' => 'connected',
            'cloud_connected_at' => now(),
            'platform_connected_at' => now(),
            'cloud_pairing_public_id' => null,
            'platform_pairing_public_id' => null,
            'cloud_pairing_code' => null,
            'platform_pairing_code' => null,
            'cloud_pairing_expires_at' => null,
            'platform_pairing_expires_at' => null,
            'ark_mail_service_url' => $baseUrl,
            'ark_mail_tenant_public_id' => $shopPublicId,
            'ark_mail_status' => 'connected',
            'ark_mail_connected_at' => now(),
            'ark_mail_credential' => null,
        ]);
    }

    public function markSuspended(): void
    {
        $this->settings->persistTrusted([
            'cloud_status' => 'suspended',
            'platform_status' => 'suspended',
            'ark_mail_status' => 'suspended',
        ]);
    }

    public function clear(): void
    {
        $this->settings->persistTrusted([
            'cloud_status' => null,
            'platform_status' => null,
            'cloud_base_url' => null,
            'platform_base_url' => null,
            'cloud_shop_public_id' => null,
            'platform_shop_public_id' => null,
            'cloud_credential' => null,
            'platform_credential' => null,
            'cloud_connected_at' => null,
            'platform_connected_at' => null,
            'cloud_pairing_public_id' => null,
            'platform_pairing_public_id' => null,
            'cloud_pairing_code' => null,
            'platform_pairing_code' => null,
            'cloud_pairing_expires_at' => null,
            'platform_pairing_expires_at' => null,
            'ark_mail_credential' => null,
            'ark_mail_tenant_public_id' => null,
            'ark_mail_from_email' => null,
            'ark_mail_status' => null,
            'ark_mail_connected_at' => null,
        ]);
    }

    public function clearExpiredPairing(): void
    {
        if ($this->status() !== 'pairing') {
            return;
        }

        $expiresAt = $this->pairingExpiresAt();
        if ($expiresAt === null || ! $expiresAt->isPast()) {
            return;
        }

        $this->settings->persistTrusted([
            'cloud_status' => null,
            'platform_status' => null,
            'cloud_pairing_public_id' => null,
            'platform_pairing_public_id' => null,
            'cloud_pairing_code' => null,
            'platform_pairing_code' => null,
            'cloud_pairing_expires_at' => null,
            'platform_pairing_expires_at' => null,
            'ark_mail_status' => null,
        ]);
    }
}
