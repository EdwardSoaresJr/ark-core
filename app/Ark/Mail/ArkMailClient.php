<?php

namespace App\Ark\Mail;

use App\Ark\Platform\PlatformConnection;
use App\Ark\Install\InstallationIdentity;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class ArkMailClient
{
    public function isConfigured(): bool
    {
        $cloud = PlatformConnection::current();

        return $cloud->isConnected()
            && filled(ShopSettings::current()->ark_mail_from_email);
    }

    public function send(TransactionalMailEnvelope $envelope): TransactionalMailResult
    {
        $cloud = PlatformConnection::current();
        $base = $cloud->baseUrl();
        $path = '/api/v1/services/mail/messages/transactional';
        $credential = (string) $cloud->credential();
        $installationUuid = InstallationIdentity::uuid();

        $attachments = [];
        foreach ($envelope->attachments as $attachment) {
            $bytes = null;
            if (isset($attachment['content'])) {
                $bytes = $attachment['content'];
            } elseif (isset($attachment['path']) && is_file($attachment['path'])) {
                $bytes = file_get_contents($attachment['path']);
            }
            if ($bytes === null || $bytes === false) {
                continue;
            }
            $attachments[] = [
                'filename' => $attachment['filename'] ?? $attachment['name'] ?? 'attachment',
                'mime' => $attachment['mime'] ?? 'application/octet-stream',
                'content_base64' => base64_encode($bytes),
            ];
        }

        $body = [
            'operation' => $envelope->operation->value,
            'to' => $envelope->recipientEmail,
            'subject' => $envelope->subject,
            'html_body' => $envelope->htmlBody,
            'text_body' => $envelope->textBody,
            'idempotency_key' => $envelope->idempotencyKey,
            'correlation_id' => $envelope->correlationId ?? (string) Str::uuid(),
            'domain_object_type' => $envelope->domainObjectType,
            'domain_object_id' => $envelope->domainObjectId,
            'attachments' => $attachments,
        ];

        $raw = json_encode($body, JSON_THROW_ON_ERROR);
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
            Log::warning('ark_mail.client.http_error', [
                'correlation_id' => $body['correlation_id'],
                'error' => $e->getMessage(),
            ]);

            return TransactionalMailResult::providerError('ARK Email is unavailable.');
        }

        $json = $response->json() ?? [];
        $correlationId = is_string($json['correlation_id'] ?? null) ? $json['correlation_id'] : $body['correlation_id'];

        if ($response->successful() && ($json['ok'] ?? false) === true) {
            return TransactionalMailResult::providerSent($correlationId, [
                'message_id' => $json['message_id'] ?? null,
                'provider_message_id' => $json['provider_message_id'] ?? null,
            ]);
        }

        $reason = is_string($json['reason_code'] ?? null) ? $json['reason_code'] : 'rejected';
        $message = is_string($json['message'] ?? null) ? $json['message'] : 'ARK Email rejected the message.';

        if (in_array($reason, ['tenant_suspended', 'installation_suspended', 'installation_revoked'], true)) {
            $cloud->markSuspended();
        }

        Log::info('ark_mail.client.rejected', [
            'correlation_id' => $correlationId,
            'reason_code' => $reason,
        ]);

        return TransactionalMailResult::rejected($reason, $message, $correlationId);
    }
}
