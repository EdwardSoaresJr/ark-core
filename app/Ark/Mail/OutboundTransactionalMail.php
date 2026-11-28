<?php

namespace App\Ark\Mail;

use App\Ark\Installation\Mail\InstallationMailConfiguration;
use App\Ark\Installation\Mail\InstallationMailSender;
use App\Ark\Platform\PlatformConnection;

/**
 * Customer transactional email: Platform/managed when connected, else standalone installation SMTP.
 * Hosted SaaS never uses installation SMTP.
 */
class OutboundTransactionalMail
{
    public function __construct(
        private readonly ArkMailClient $arkMail,
        private readonly InstallationMailConfiguration $installation,
        private readonly InstallationMailSender $installationSender,
    ) {}

    /**
     * @return 'ark_mail'|'installation'|'none'
     */
    public function providerMode(): string
    {
        if ($this->arkMail->isConfigured()) {
            return 'ark_mail';
        }

        if ($this->installation->allowsCustomerDelivery()) {
            return 'installation';
        }

        return 'none';
    }

    public function isReady(): bool
    {
        return $this->providerMode() !== 'none';
    }

    public function statusLabel(): string
    {
        $cloud = PlatformConnection::current();

        return match (true) {
            $cloud->isSuspended() => 'Suspended',
            $cloud->isPairing() => 'Pairing',
            $this->providerMode() === 'ark_mail' => 'ARK Email',
            $this->providerMode() === 'installation' => match ($this->installation->activeSource()) {
                InstallationMailConfiguration::SOURCE_SHOP => 'Installation SMTP (Settings)',
                InstallationMailConfiguration::SOURCE_ENVIRONMENT => 'Installation SMTP (environment)',
                default => 'Installation SMTP',
            },
            default => 'Not configured',
        };
    }

    /**
     * @param  array<string, mixed>  $variables
     * @param  list<array{filename: string, mime: string, path?: string, content?: string, content_base64?: string}>  $attachments
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
        if ($this->arkMail->isConfigured()) {
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

        if ($this->installation->allowsCustomerDelivery()) {
            return $this->installationSender->send(
                $operation->value,
                $recipientEmail,
                $variables,
                $attachments,
            );
        }

        return TransactionalMailResult::notConfigured();
    }

    public function ensureReadyOrResult(): ?TransactionalMailResult
    {
        if ($this->isReady()) {
            return null;
        }

        return TransactionalMailResult::notConfigured();
    }
}
