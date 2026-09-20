<?php

namespace App\Ark\Platform;

use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Platform\Cloud\CloudUrls;
use App\Ark\Platform\ShopBaseUrl;

/**
 * Settings → ARK Platform service list.
 *
 * Box connection state is local (PlatformConnection). Managed-service rows come
 * from ARK Platform when connected — Core renders; Cloud is authority.
 */
final class PlatformServiceCatalog
{
    public function __construct(
        private readonly ShopSettings $settings,
        private readonly PlatformConnection $cloud,
        private readonly PlatformStatusClient $statusClient,
    ) {}

    public static function forCurrentShop(): self
    {
        return new self(
            ShopSettings::current(),
            PlatformConnection::current(),
            app(PlatformStatusClient::class),
        );
    }

    /**
     * @return array{
     *     shop_name: string,
     *     box_host: string,
     *     box_origin: string,
     *     cloud_connected: bool,
     *     cloud_pairing: bool,
     *     cloud_suspended: bool,
     *     cloud_base_url: ?string,
     *     cloud_shop_public_id: ?string,
     *     connection_label: string,
     *     connection_tone: string,
     * }
     */
    public function connectionSummary(): array
    {
        $shopName = trim((string) ($this->settings->shop_name ?: config('app.name')));

        return [
            'shop_name' => $shopName,
            'box_host' => ShopBaseUrl::host(),
            'box_origin' => ShopBaseUrl::origin(),
            'cloud_connected' => $this->cloud->isConnected(),
            'cloud_pairing' => $this->cloud->isPairing(),
            'cloud_suspended' => $this->cloud->isSuspended(),
            'cloud_base_url' => filled($this->cloud->baseUrl()) ? $this->cloud->baseUrl() : null,
            'cloud_shop_public_id' => $this->cloud->shopPublicId(),
            'connection_label' => $this->connectionLabel(),
            'connection_tone' => $this->connectionTone(),
        ];
    }

    public function manageUrl(): ?string
    {
        if (! $this->cloud->isConnected() && ! $this->cloud->isSuspended() && ! $this->cloud->isPairing()) {
            return CloudUrls::login() ?? CloudUrls::dashboard();
        }

        return CloudUrls::go('shop', $this->cloud->shopPublicId())
            ?? CloudUrls::dashboard()
            ?? CloudUrls::login();
    }

    /**
     * @return array{active: bool, used: int, limit: int, period_key: string}|null
     */
    public function starterSummary(): ?array
    {
        if (! $this->cloud->isConnected()) {
            return null;
        }

        $status = $this->statusClient->fetch();
        $starter = is_array($status) ? ($status['starter'] ?? null) : null;

        return is_array($starter) ? $starter : null;
    }

    /**
     * @return list<array{
     *     key: string,
     *     label: string,
     *     status: string,
     *     status_label: string,
     *     detail: ?string,
     *     manage_url: ?string,
     * }>
     */
    public function services(): array
    {
        $manageUrl = $this->manageUrl();

        if ($this->cloud->isSuspended()) {
            return $this->offlineCatalog('suspended', 'Suspended', $manageUrl);
        }

        if (! $this->cloud->isConnected()) {
            return $this->offlineCatalog('requires_cloud', 'Requires ARK Platform', null);
        }

        $status = $this->statusClient->fetchAndPersistLocalMailProjection();
        if ($status === null) {
            return [[
                'key' => 'connect',
                'label' => 'ARK Connect',
                'status' => 'unavailable',
                'status_label' => 'Unavailable',
                'detail' => 'Could not load service status from ARK Platform.',
                'manage_url' => $manageUrl,
            ]];
        }

        $shopId = $this->cloud->shopPublicId();
        $rows = collect($status['services'])
            ->map(function (array $row) use ($shopId, $manageUrl): array {
                $key = (string) $row['key'];
                $serviceManage = match ($key) {
                    'mail' => CloudUrls::go('services.mail', $shopId),
                    'sms' => CloudUrls::go('services.sms', $shopId),
                    'voice' => CloudUrls::go('services.voice', $shopId),
                    'connect' => CloudUrls::go('shop', $shopId),
                    default => CloudUrls::go('services', $shopId) ?? $manageUrl,
                };

                return [
                    'key' => $key,
                    'label' => $row['label'],
                    'status' => $row['status'],
                    'status_label' => $row['status_label'],
                    'detail' => $row['detail'],
                    'manage_url' => $serviceManage ?? $manageUrl,
                ];
            })
            ->values()
            ->all();

        $starter = $status['starter'] ?? null;
        if (is_array($starter) && ($starter['active'] ?? false)) {
            array_unshift($rows, [
                'key' => 'starter',
                'label' => 'ARK Platform Starter',
                'status' => 'active',
                'status_label' => 'Free',
                'detail' => sprintf(
                    '%d of %d included Cloud-enabled repair orders used this month',
                    (int) ($starter['used'] ?? 0),
                    (int) ($starter['limit'] ?? 20),
                ),
                'manage_url' => $manageUrl,
            ]);
        }

        return $rows;
    }

    /**
     * @return list<array{key: string, label: string, status: string, status_label: string, detail: ?string, manage_url: ?string}>
     */
    private function offlineCatalog(string $status, string $statusLabel, ?string $manageUrl): array
    {
        $labels = [
            'connect' => 'ARK Connect',
            'mail' => 'ARK Email',
            'sms' => 'ARK Texting',
            'voice' => 'ARK Voice',
            'dragon' => 'Dragon AI',
            'backup' => 'ARK Backup',
            'storage' => 'ARK Storage',
            'data' => 'ARK Data',
        ];

        return collect($labels)
            ->map(fn (string $label, string $key): array => [
                'key' => $key,
                'label' => $label,
                'status' => $key === 'backup' || $key === 'storage' || $key === 'data'
                    ? ($status === 'requires_cloud' ? 'requires_cloud' : 'coming_soon')
                    : $status,
                'status_label' => $key === 'backup' || $key === 'storage' || $key === 'data'
                    ? ($status === 'requires_cloud' ? $statusLabel : 'Coming Soon')
                    : $statusLabel,
                'detail' => null,
                'manage_url' => $manageUrl,
            ])
            ->values()
            ->all();
    }

    private function connectionLabel(): string
    {
        return match (true) {
            $this->cloud->isSuspended() => 'Suspended',
            $this->cloud->isConnected() => 'Connected',
            $this->cloud->isPairing() => 'Connecting',
            default => 'Not connected',
        };
    }

    private function connectionTone(): string
    {
        return match (true) {
            $this->cloud->isSuspended() => 'danger',
            $this->cloud->isConnected() => 'success',
            $this->cloud->isPairing() => 'warning',
            default => 'muted',
        };
    }
}
