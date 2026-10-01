<?php

namespace App\Ark\Mail;

use App\Ark\Platform\PlatformConnection;

/**
 * Asks Platform to send a transactional email. Core does not deliver it.
 */
class OutboundTransactionalMail
{
    public function __construct(
        private readonly ArkMailClient $arkMail,
    ) {}

    /**
     * @return 'ark_mail'|'none'
     */
    public function providerMode(): string
    {
        if ($this->arkMail->isConfigured()) {
            return 'ark_mail';
        }

        return 'none';
    }

    public function isReady(): bool
    {
        return $this->providerMode() === 'ark_mail';
    }

    public function statusLabel(): string
    {
        $cloud = PlatformConnection::current();

        return match (true) {
            $cloud->isSuspended() => 'Suspended',
            $cloud->isPairing() => 'Pairing',
            $this->providerMode() === 'ark_mail' => 'ARK Email',
            default => 'Not configured',
        };
    }

    /**
     * @param  array<string, mixed>  $variables
     * @param  list<array{filename: string, mime: string, path?: string, content?: string}>  $attachments
     */
    public function sendIntent(
        TransactionalMailOperation $operation,
        string $recipientEmail,
        array $variables,
        string $idempotencyKey,
        ?string $domainObjectType = null,
        ?string $domainObjectId = null,
        array $attachments = [],
    ): TransactionalMailResult {
        if ($this->providerMode() !== 'ark_mail') {
            return TransactionalMailResult::notConfigured();
        }

        return $this->arkMail->send(TransactionalMailEnvelope::intent(
            $operation,
            $recipientEmail,
            $variables,
            $idempotencyKey,
            $domainObjectType,
            $domainObjectId,
            $attachments,
        ));
    }

    public function ensureReadyOrResult(): ?TransactionalMailResult
    {
        if ($this->isReady()) {
            return null;
        }

        return TransactionalMailResult::notConfigured();
    }
}
