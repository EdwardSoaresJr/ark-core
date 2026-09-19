<?php

namespace App\Ark\Platform;

use App\Ark\Install\InstallationIdentity;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class ArkBoxHeartbeatClient
{
    /**
     * @return array{ok: bool, health?: string, last_heartbeat_at?: string, reported_version?: ?string, reason_code?: string, message?: string}
     */
    public function send(): array
    {
        $cloud = PlatformConnection::current();
        if (! $cloud->isConnected()) {
            return [
                'ok' => false,
                'reason_code' => 'not_connected',
                'message' => 'ARK Platform is not connected.',
            ];
        }

        $path = '/api/v1/box/heartbeat';
        $body = array_filter(BoxRuntimeObservation::payload(), fn ($value) => $value !== null);
        $raw = json_encode($body, JSON_THROW_ON_ERROR);
        $credential = (string) $cloud->credential();
        $timestamp = (string) time();
        $nonce = Str::random(24);
        $signature = hash_hmac('sha256', implode("\n", [
            $timestamp,
            $nonce,
            'POST',
            $path,
            hash('sha256', $raw),
        ]), $credential);

        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'X-Ark-Installation-Id' => InstallationIdentity::uuid(),
                'X-Ark-Timestamp' => $timestamp,
                'X-Ark-Nonce' => $nonce,
                'X-Ark-Signature' => $signature,
            ])->timeout(8)->withBody($raw, 'application/json')->post(rtrim($cloud->baseUrl(), '/').$path);
        } catch (\Throwable $e) {
            Log::warning('ark_platform.heartbeat.http_error', [
                'error' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'reason_code' => 'provider_unavailable',
                'message' => 'ARK Platform heartbeat failed.',
            ];
        }

        $json = $response->json() ?? [];
        if (! is_array($json)) {
            $json = [];
        }

        if ($response->successful() && ($json['ok'] ?? false) === true) {
            return $json;
        }

        return array_merge([
            'ok' => false,
            'reason_code' => is_string($json['reason_code'] ?? null) ? $json['reason_code'] : 'rejected',
            'message' => is_string($json['message'] ?? null) ? $json['message'] : 'ARK Platform heartbeat was rejected.',
        ], $json);
    }
}
