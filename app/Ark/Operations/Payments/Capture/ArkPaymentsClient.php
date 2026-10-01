<?php

namespace App\Ark\Operations\Payments\Capture;

use App\Ark\Install\InstallationIdentity;
use App\Ark\Platform\PlatformConnection;
use App\Ark\Platform\PlatformSignedRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Core → Platform Payment Capture client (provider-neutral).
 */
final class ArkPaymentsClient
{
    /**
     * @param  array<string, mixed>  $body
     * @return array{ok: bool, status: int, body: array<string, mixed>, unavailable?: bool, reason_code?: string}
     */
    public function createCapture(array $body): array
    {
        return $this->request('POST', '/api/v1/services/payments/captures', $body);
    }

    /**
     * @return array{ok: bool, status: int, body: array<string, mixed>, unavailable?: bool, reason_code?: string}
     */
    public function captureStatus(string $idempotencyKey): array
    {
        return $this->request('GET', '/api/v1/services/payments/captures/'.$idempotencyKey, []);
    }

    /**
     * @return array{ok: bool, status: int, body: array<string, mixed>, unavailable?: bool, reason_code?: string}
     */
    public function listDevices(): array
    {
        return $this->request('GET', '/api/v1/services/payments/devices', []);
    }

    /**
     * @return array{ok: bool, status: int, body: array<string, mixed>, unavailable?: bool, reason_code?: string}
     */
    public function readiness(): array
    {
        return $this->request('GET', '/api/v1/services/payments/readiness', []);
    }

    /**
     * @return array{ok: bool, status: int, body: array<string, mixed>, unavailable?: bool, reason_code?: string}
     */
    public function cancelCapture(string $idempotencyKey): array
    {
        return $this->request('POST', '/api/v1/services/payments/captures/'.$idempotencyKey.'/cancel', []);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{ok: bool, status: int, body: array<string, mixed>, unavailable?: bool, reason_code?: string}
     */
    private function request(string $method, string $path, array $body): array
    {
        $cloud = PlatformConnection::current();
        if (! $cloud->isConnected() || ! filled($cloud->credential())) {
            return [
                'ok' => false,
                'status' => 0,
                'body' => [],
                'unavailable' => true,
                'reason_code' => 'cloud_unavailable',
            ];
        }

        $base = rtrim((string) $cloud->baseUrl(), '/');
        $raw = $method === 'GET' ? '[]' : json_encode($body, JSON_THROW_ON_ERROR);

        try {
            $headers = PlatformSignedRequest::headers($method, $path, $raw, (string) $cloud->credential());
            $pending = Http::withHeaders($headers)->timeout(20);

            $response = match (strtoupper($method)) {
                'GET' => $pending->withBody($raw, 'application/json')->get($base.$path),
                default => $pending->withBody($raw, 'application/json')->post($base.$path),
            };
        } catch (\Throwable $e) {
            Log::warning('ark_payments.cloud_unavailable', [
                'path' => $path,
                'error' => $e->getMessage(),
                'installation' => InstallationIdentity::uuid(),
            ]);

            return [
                'ok' => false,
                'status' => 0,
                'body' => [],
                'unavailable' => true,
                'reason_code' => 'cloud_unavailable',
            ];
        }

        $json = $response->json();
        $payload = is_array($json) ? $json : [];

        if ($response->status() === 403 && ($payload['reason_code'] ?? '') === 'not_entitled') {
            return [
                'ok' => false,
                'status' => 403,
                'body' => $payload,
                'reason_code' => 'entitlement_unavailable',
            ];
        }

        if (in_array($payload['reason_code'] ?? '', ['installation_suspended', 'installation_revoked'], true)) {
            $cloud->markSuspended();
        }

        return [
            'ok' => $response->successful() && (($payload['ok'] ?? true) !== false || isset($payload['status'])),
            'status' => $response->status(),
            'body' => $payload,
            'reason_code' => isset($payload['reason_code']) ? (string) $payload['reason_code'] : null,
        ];
    }
}
