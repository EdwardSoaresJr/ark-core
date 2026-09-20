<?php

namespace App\Ark\Texting;

use App\Ark\Install\InstallationIdentity;
use App\Ark\Platform\PlatformConnection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Box → Platform ARK Texting client. Provider credentials never leave Platform.
 */
final class ArkTextingClient
{
    public function isConfigured(): bool
    {
        return PlatformConnection::current()->isConnected();
    }

    /**
     * @param  list<string>  $mediaUrls
     * @return array{ok: bool, message_id?: string, provider_message_id?: string, status?: string, reason_code?: string, message?: string, correlation_id?: string}
     */
    public function sendConversationMessage(
        string $toPhone,
        string $body,
        string $idempotencyKey,
        ?string $domainObjectType = null,
        ?string $domainObjectId = null,
        array $mediaUrls = [],
        ?string $correlationId = null,
    ): array {
        $cloud = PlatformConnection::current();
        $base = $cloud->baseUrl();
        $path = '/api/v1/services/sms/messages/conversation';
        $credential = (string) $cloud->credential();
        $installationUuid = InstallationIdentity::uuid();

        $payload = [
            'operation' => 'conversation.send',
            'to' => $toPhone,
            'body' => $body,
            'idempotency_key' => $idempotencyKey,
            'correlation_id' => $correlationId ?? (string) Str::uuid(),
            'domain_object_type' => $domainObjectType,
            'domain_object_id' => $domainObjectId,
            'media_urls' => $mediaUrls,
        ];

        $raw = json_encode($payload, JSON_THROW_ON_ERROR);
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
                'X-Ark-Installation-Id' => $installationUuid,
                'X-Ark-Timestamp' => $timestamp,
                'X-Ark-Nonce' => $nonce,
                'X-Ark-Signature' => $signature,
            ])->withBody($raw, 'application/json')
                ->timeout(20)
                ->post($base.$path);
        } catch (\Throwable $e) {
            Log::warning('ark_texting.client.http_error', [
                'correlation_id' => $payload['correlation_id'],
                'error' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'reason_code' => 'provider_unavailable',
                'message' => 'ARK Texting is unavailable.',
                'correlation_id' => $payload['correlation_id'],
            ];
        }

        $json = $response->json() ?? [];
        $correlation = is_string($json['correlation_id'] ?? null) ? $json['correlation_id'] : $payload['correlation_id'];

        if ($response->successful() && ($json['ok'] ?? false) === true) {
            return [
                'ok' => true,
                'message_id' => is_string($json['message_id'] ?? null) ? $json['message_id'] : null,
                'provider_message_id' => is_string($json['provider_message_id'] ?? null) ? $json['provider_message_id'] : null,
                'status' => is_string($json['status'] ?? null) ? $json['status'] : 'provider_sent',
                'correlation_id' => $correlation,
            ];
        }

        $reason = is_string($json['reason_code'] ?? null) ? $json['reason_code'] : 'rejected';
        $message = is_string($json['message'] ?? null) ? $json['message'] : 'ARK Texting rejected the message.';

        if (in_array($reason, ['tenant_suspended', 'installation_suspended', 'installation_revoked'], true)) {
            $cloud->markSuspended();
        }

        Log::info('ark_texting.client.rejected', [
            'correlation_id' => $correlation,
            'reason_code' => $reason,
        ]);

        return [
            'ok' => false,
            'reason_code' => $reason,
            'message' => $message,
            'correlation_id' => $correlation,
            'message_id' => is_string($json['message_id'] ?? null) ? $json['message_id'] : null,
        ];
    }
}
