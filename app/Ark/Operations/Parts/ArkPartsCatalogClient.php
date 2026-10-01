<?php

namespace App\Ark\Operations\Parts;

use App\Ark\Install\InstallationIdentity;
use App\Ark\Platform\PlatformConnection;
use App\Ark\Platform\PlatformSignedRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Core → Platform parts catalog client. No PartsTech SDK in Public Core.
 */
final class ArkPartsCatalogClient
{
    /**
     * @return array{ok: bool, status: int, body: array<string, mixed>, unavailable?: bool, reason_code?: string}
     */
    public function readiness(): array
    {
        return $this->request('GET', '/api/v1/services/parts/readiness', []);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{ok: bool, status: int, body: array<string, mixed>, unavailable?: bool, reason_code?: string}
     */
    public function prepareSession(array $body): array
    {
        return $this->request('POST', '/api/v1/services/parts/sessions/prepare', $body);
    }

    /**
     * @return array{ok: bool, status: int, body: array<string, mixed>, unavailable?: bool, reason_code?: string}
     */
    public function quoteLines(string $cartReference): array
    {
        $path = '/api/v1/services/parts/sessions/'.rawurlencode($cartReference).'/quote';

        return $this->request('GET', $path, []);
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
        $raw = strtoupper($method) === 'GET' ? '[]' : json_encode($body, JSON_THROW_ON_ERROR);

        try {
            $headers = PlatformSignedRequest::headers($method, $path, $raw, (string) $cloud->credential());
            $pending = Http::withHeaders($headers)->timeout(20);

            $response = match (strtoupper($method)) {
                'GET' => $pending->withBody($raw, 'application/json')->get($base.$path),
                default => $pending->withBody($raw, 'application/json')->post($base.$path),
            };
        } catch (\Throwable $e) {
            Log::warning('ark_parts.cloud_unavailable', [
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

        return [
            'ok' => $response->successful() && (($json['ok'] ?? false) === true),
            'status' => $response->status(),
            'body' => is_array($json) ? $json : [],
            'reason_code' => is_array($json) ? ($json['reason_code'] ?? null) : null,
        ];
    }
}
