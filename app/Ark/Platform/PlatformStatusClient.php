<?php

namespace App\Ark\Platform;

use App\Ark\Install\InstallationIdentity;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Fetches the Cloud-authored service projection for this Box.
 */
final class PlatformStatusClient
{
    /**
     * @return array{
     *     ok: bool,
     *     services: list<array{key: string, label: string, status: string, status_label: string, detail: ?string}>,
     *     from_email: ?string,
     *     reply_to: ?string,
     *     shop_public_id: ?string,
     * }|null
     */
    public function fetchAndPersistLocalMailProjection(): ?array
    {
        $status = $this->fetch();
        if ($status === null) {
            return null;
        }

        $this->persistLocalFromAddress($status);

        return $status;
    }

    /**
     * @param  array{from_email?: ?string, reply_to?: ?string}  $status
     */
    public function persistLocalFromAddress(array $status): void
    {
        $from = $status['from_email'] ?? null;
        if (! is_string($from) || ! filled(trim($from))) {
            return;
        }

        $normalized = strtolower(trim($from));
        $settings = ShopSettings::current();
        if ($settings->ark_mail_from_email === $normalized) {
            return;
        }

        $settings->persistTrusted([
            'ark_mail_from_email' => $normalized,
        ]);
    }

    /**
     * @return array{
     *     ok: bool,
     *     services: list<array{key: string, label: string, status: string, status_label: string, detail: ?string}>,
     *     from_email: ?string,
     *     reply_to: ?string,
     *     shop_public_id: ?string,
     * }|null
     */
    public function fetch(): ?array
    {
        $cloud = PlatformConnection::current();
        if (! $cloud->isConnected()) {
            return null;
        }

        $base = $cloud->baseUrl();
        $path = '/api/v1/status';
        $credential = (string) $cloud->credential();
        $installationUuid = InstallationIdentity::uuid();
        $rawBody = '';
        $timestamp = (string) time();
        $nonce = Str::random(24);
        $signature = hash_hmac('sha256', implode("\n", [
            $timestamp,
            $nonce,
            'GET',
            $path,
            hash('sha256', $rawBody),
        ]), $credential);

        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'X-Ark-Installation-Id' => $installationUuid,
                'X-Ark-Timestamp' => $timestamp,
                'X-Ark-Nonce' => $nonce,
                'X-Ark-Signature' => $signature,
            ])->timeout(12)->get($base.$path);
        } catch (\Throwable $e) {
            Log::warning('ark_cloud.status_unavailable', ['error' => $e->getMessage()]);

            return null;
        }

        $json = $response->json();
        if (! $response->successful() || ! is_array($json) || ($json['ok'] ?? false) !== true) {
            Log::warning('ark_cloud.status_rejected', [
                'status' => $response->status(),
                'reason' => is_array($json) ? ($json['reason_code'] ?? null) : null,
            ]);

            return null;
        }

        $services = [];
        foreach ($json['services'] ?? [] as $row) {
            if (! is_array($row) || ! isset($row['key'], $row['status'], $row['status_label'])) {
                continue;
            }
            $services[] = [
                'key' => (string) $row['key'],
                'label' => (string) ($row['label'] ?? $row['key']),
                'status' => (string) $row['status'],
                'status_label' => (string) $row['status_label'],
                'detail' => isset($row['detail']) && is_string($row['detail']) ? $row['detail'] : null,
                'runtime_owner' => isset($row['runtime_owner']) && is_string($row['runtime_owner'])
                    ? strtolower(trim($row['runtime_owner']))
                    : null,
            ];
        }

        return [
            'ok' => true,
            'services' => $services,
            'from_email' => isset($json['from_email']) && is_string($json['from_email']) ? $json['from_email'] : null,
            'reply_to' => isset($json['reply_to']) && is_string($json['reply_to']) ? $json['reply_to'] : null,
            'shop_public_id' => isset($json['shop_public_id']) && is_string($json['shop_public_id']) ? $json['shop_public_id'] : null,
            'starter' => isset($json['starter']) && is_array($json['starter']) ? [
                'active' => (bool) ($json['starter']['active'] ?? false),
                'used' => (int) ($json['starter']['used'] ?? 0),
                'limit' => (int) ($json['starter']['limit'] ?? 20),
                'period_key' => (string) ($json['starter']['period_key'] ?? ''),
            ] : null,
        ];
    }
}
