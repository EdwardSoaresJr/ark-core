<?php

namespace App\Ark\Installation\Mail;

use App\Ark\Mail\OutboundTransactionalMail;
use App\Ark\Mail\TransactionalMailOperation;
use App\Ark\Mail\TransactionalMailResult;
use App\Ark\Platform\Mail\ArkMailClient as PlatformArkMailClient;
use App\Ark\Platform\Mail\ManagedMailGate;
use Illuminate\Support\Str;

/**
 * Single entry for customer transactional mail used by document and statement senders.
 * Prefer Platform/managed delivery when available; otherwise standalone installation SMTP.
 * Hosted SaaS never falls back to installation SMTP.
 */
final class CustomerOutboundMail
{
    public function __construct(
        private readonly PlatformArkMailClient $platformMail,
        private readonly InstallationMailConfiguration $installation,
        private readonly InstallationMailSender $installationSender,
        private readonly OutboundTransactionalMail $outbound,
    ) {}

    public function canSend(): bool
    {
        return $this->outbound->isReady();
    }

    /**
     * @return 'ark_mail'|'installation'|'none'
     */
    public function providerMode(): string
    {
        return $this->outbound->providerMode();
    }

    public function statusLabel(): string
    {
        return $this->outbound->statusLabel();
    }

    /**
     * @param  array<string, mixed>  $variables
     * @param  list<array{filename: string, mime?: string, path?: string, content?: string, content_base64?: string}>  $attachments
     * @param  array<string, mixed>  $metadata
     */
    public function send(
        string $operation,
        string $recipientEmail,
        array $variables = [],
        ?string $idempotencyKey = null,
        ?string $domainObjectType = null,
        ?string $domainObjectId = null,
        array $attachments = [],
        array $metadata = [],
    ): TransactionalMailResult {
        $idempotencyKey ??= Str::uuid()->toString();
        $correlationId = 'cust-mail-'.Str::uuid();
        $normalized = $this->normalizeAttachmentsForPlatform($attachments);

        $enum = TransactionalMailOperation::tryFrom($operation);
        if ($enum !== null && $this->outbound->providerMode() === 'ark_mail') {
            return $this->outbound->sendIntent(
                $enum,
                $recipientEmail,
                $variables,
                $idempotencyKey,
                $domainObjectType,
                $domainObjectId,
                $normalized,
            );
        }

        if ($enum !== null && $this->outbound->providerMode() === 'installation') {
            return $this->outbound->sendIntent(
                $enum,
                $recipientEmail,
                $variables,
                $idempotencyKey,
                $domainObjectType,
                $domainObjectId,
                $attachments,
            );
        }

        // Non-enum operations (e.g. installation.mail.test, statement.send if not in enum).
        if (ManagedMailGate::platformSend() && $this->platformMail->isConfigured()) {
            $result = $this->platformMail->sendTransactional([
                'operation' => $operation,
                'to' => $recipientEmail,
                'variables' => $variables,
                'idempotency_key' => $idempotencyKey,
                'domain_object_type' => $domainObjectType,
                'domain_object_id' => $domainObjectId,
                'attachments' => $normalized,
                'metadata' => $metadata,
            ]);

            if (($result['ok'] ?? false) === true) {
                return TransactionalMailResult::providerSent(
                    is_string($result['correlation_id'] ?? null) ? $result['correlation_id'] : $correlationId,
                    ['operation' => $operation],
                );
            }

            return TransactionalMailResult::providerError(
                is_string($result['message'] ?? null) ? $result['message'] : 'Email could not be sent.',
                is_string($result['correlation_id'] ?? null) ? $result['correlation_id'] : $correlationId,
            );
        }

        if ($this->installation->hostedIsolated()) {
            return TransactionalMailResult::rejected(
                'hosted_isolation',
                'Hosted installations use managed email delivery only.',
                $correlationId,
            );
        }

        if (! $this->installation->allowsCustomerDelivery()) {
            return TransactionalMailResult::notConfigured();
        }

        return $this->installationSender->send(
            $operation,
            $recipientEmail,
            $variables,
            $attachments,
            $correlationId,
        );
    }

    /**
     * @param  list<array{filename: string, mime?: string, path?: string, content?: string, content_base64?: string}>  $attachments
     * @return list<array{filename: string, mime: string, content_base64: string}>
     */
    private function normalizeAttachmentsForPlatform(array $attachments): array
    {
        $normalized = [];
        foreach ($attachments as $attachment) {
            $filename = (string) ($attachment['filename'] ?? 'attachment');
            $mime = (string) ($attachment['mime'] ?? 'application/octet-stream');
            $base64 = null;

            if (isset($attachment['content_base64']) && is_string($attachment['content_base64'])) {
                $base64 = $attachment['content_base64'];
            } elseif (isset($attachment['content']) && is_string($attachment['content'])) {
                $base64 = base64_encode($attachment['content']);
            } elseif (isset($attachment['path']) && is_string($attachment['path']) && is_file($attachment['path'])) {
                $bytes = file_get_contents($attachment['path']);
                if (is_string($bytes)) {
                    $base64 = base64_encode($bytes);
                }
            }

            if ($base64 === null || $base64 === '') {
                continue;
            }

            $normalized[] = [
                'filename' => $filename,
                'mime' => $mime,
                'content_base64' => $base64,
            ];
        }

        return $normalized;
    }
}
