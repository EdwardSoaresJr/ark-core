<?php

namespace App\Ark\Platform\Communications;

use App\Ark\Install\InstallationIdentity;
use App\Ark\Platform\PlatformConnection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Core → Platform managed Communications client.
 */
final class ArkCommunicationsClient
{
    public function isConfigured(): bool
    {
        return PlatformConnection::current()->isConnected();
    }

    /**
     * @param  list<string>  $mediaUrls
     * @return array{ok: bool, message_id?: string|null, comm_message_public_id?: string|null, provider_message_id?: string|null, status?: string, reason_code?: string, message?: string, correlation_id?: string}
     */
    public function sendConversationMessage(
        string $toPhone,
        string $body,
        string $idempotencyKey,
        ?string $domainObjectType = null,
        ?string $domainObjectId = null,
        array $mediaUrls = [],
        ?string $correlationId = null,
        ?int $coreActorUserId = null,
    ): array {
        $payload = [
            'operation' => 'conversation.send',
            'to' => $toPhone,
            'body' => $body,
            'idempotency_key' => $idempotencyKey,
            'correlation_id' => $correlationId ?? (string) Str::uuid(),
            'domain_object_type' => $domainObjectType,
            'domain_object_id' => $domainObjectId,
            'media_urls' => $mediaUrls,
            'metadata' => array_filter([
                'core_actor_user_id' => $coreActorUserId,
            ]),
        ];

        return $this->request('POST', '/api/v1/services/sms/messages/conversation', $payload);
    }

    /**
     * @return array{ok: bool, conversations?: list<array<string, mixed>>, reason_code?: string, message?: string}
     */
    public function listConversations(?int $coreUserId = null, int $limit = 50): array
    {
        $query = ['limit' => $limit];
        if ($coreUserId !== null) {
            $query['core_user_id'] = $coreUserId;
        }

        return $this->request('GET', '/api/v1/services/communications/conversations', null, $query);
    }

    /**
     * @return array{ok: bool, conversation?: array<string, mixed>, messages?: list<array<string, mixed>>, reason_code?: string}
     */
    public function showConversation(string $publicId, int $limit = 100): array
    {
        return $this->request(
            'GET',
            '/api/v1/services/communications/conversations/'.$publicId,
            null,
            ['limit' => $limit],
        );
    }

    /**
     * @return array{ok: bool, reason_code?: string}
     */
    public function markRead(string $publicId, int $coreUserId): array
    {
        return $this->request('POST', '/api/v1/services/communications/conversations/'.$publicId.'/read', [
            'core_user_id' => $coreUserId,
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $body
     * @param  array<string, scalar>|null  $query
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, ?array $body = null, ?array $query = null): array
    {
        $cloud = PlatformConnection::current();
        $base = $cloud->baseUrl();
        $credential = (string) $cloud->credential();
        $installationUuid = InstallationIdentity::uuid();

        $raw = $body !== null ? json_encode($body, JSON_THROW_ON_ERROR) : '';
        // GET signing in Platform tests uses "[]" - match Authenticate middleware getContent().
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

        $url = $base.$path;
        if ($query !== null && $query !== []) {
            $url .= '?'.http_build_query($query);
        }

        try {
            $pending = Http::withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'X-Ark-Installation-Id' => $installationUuid,
                'X-Ark-Timestamp' => $timestamp,
                'X-Ark-Nonce' => $nonce,
                'X-Ark-Signature' => $signature,
            ])->timeout(20);

            $response = match (strtoupper($method)) {
                'GET' => $pending->withBody($raw, 'application/json')->get($url),
                'POST' => $pending->withBody($raw, 'application/json')->post($url),
                'PATCH' => $pending->withBody($raw, 'application/json')->patch($url),
                default => throw new \InvalidArgumentException('Unsupported method'),
            };
        } catch (\Throwable $e) {
            Log::warning('ark_comms.client.http_error', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'reason_code' => 'provider_unavailable',
                'message' => 'ARK Communications is unavailable.',
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
            'message' => is_string($json['message'] ?? null) ? $json['message'] : 'Request rejected.',
        ], $json);
    }
}
