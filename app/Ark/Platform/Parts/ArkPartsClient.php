<?php

namespace App\Ark\Platform\Parts;

use App\Ark\Install\InstallationIdentity;
use App\Ark\Platform\PlatformConnection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class ArkPartsClient
{
    public function isConfigured(): bool
    {
        return PlatformConnection::current()->isConnected();
    }

    /**
     * @return array<string, mixed>
     */
    public function readiness(): array
    {
        return $this->request('GET', '/api/v1/services/parts/readiness', timeout: 3);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function prepareSession(array $payload): array
    {
        return $this->request('POST', '/api/v1/services/parts/sessions/prepare', $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function quoteLines(string $cartReference, array $payload = []): array
    {
        return $this->request(
            'POST',
            '/api/v1/services/parts/sessions/'.rawurlencode($cartReference).'/quote',
            $payload,
        );
    }

    /**
     * @param  array<string, mixed>|null  $body
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, ?array $body = null, int $timeout = 20): array
    {
        $cloud = PlatformConnection::current();
        $base = $cloud->baseUrl();
        $credential = (string) $cloud->credential();
        $installationUuid = InstallationIdentity::uuid();

        $raw = $body !== null ? json_encode($body, JSON_THROW_ON_ERROR) : '';
        if (strtoupper($method) === 'GET') {
            $raw = '[]';
        }

        $timestamp = (string) time();
        $nonce = Str::random(24);
        $signature = hash_hmac('sha256', implode("\n", [
            $timestamp,
            $nonce,
            strtoupper($method),
            $path,
            hash('sha256', $raw),
        ]), $credential);

        try {
            $pending = Http::withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'X-Ark-Installation-Id' => $installationUuid,
                'X-Ark-Timestamp' => $timestamp,
                'X-Ark-Nonce' => $nonce,
                'X-Ark-Signature' => $signature,
            ])->timeout($timeout);

            $response = match (strtoupper($method)) {
                'GET' => $pending->withBody($raw, 'application/json')->get($base.$path),
                'POST' => $pending->withBody($raw, 'application/json')->post($base.$path),
                default => throw new \InvalidArgumentException('Unsupported method'),
            };
        } catch (\Throwable $e) {
            Log::warning('ark_parts.client.http_error', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'http_status' => 0,
                'reason_code' => 'provider_unavailable',
                'message' => 'PartsTech Catalog is unavailable.',
            ];
        }

        $json = $response->json() ?? [];
        if (! is_array($json)) {
            $json = [];
        }

        $json['http_status'] = $response->status();

        if ($response->successful() && ($json['ok'] ?? false) === true) {
            return $json;
        }

        return array_merge([
            'ok' => false,
            'reason_code' => is_string($json['reason_code'] ?? null) ? $json['reason_code'] : 'rejected',
            'message' => is_string($json['message'] ?? null) ? $json['message'] : 'PartsTech Catalog request was rejected.',
        ], $json);
    }
}
