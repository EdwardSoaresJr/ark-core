<?php

namespace App\Ark\Mail;

use App\Ark\Cloud\CloudConnection;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;

/**
 * Provider-neutral outbound transactional email boundary.
 *
 * Stock Core delivers through ARK Mail when Cloud is connected.
 * Non-production may use log/array for local development and tests.
 * Custom/community providers can replace or subclass this binding without
 * changing estimate, invoice, or document workflows.
 *
 * No silent cross-provider fallback. MAIL_MAILER=postmark and shop-owned
 * Postmark credentials are not a stock Core production path.
 */
class OutboundTransactionalMail
{
    public function __construct(
        private readonly ArkMailClient $arkMail,
    ) {}

    /**
     * @return 'ark_mail'|'local_log'|'none'
     */
    public function providerMode(): string
    {
        if ($this->arkMail->isConfigured()) {
            return 'ark_mail';
        }

        if ($this->allowsLocalMailer()) {
            return 'local_log';
        }

        return 'none';
    }

    public function isReady(): bool
    {
        return $this->providerMode() !== 'none';
    }

    public function statusLabel(): string
    {
        $cloud = CloudConnection::current();

        return match (true) {
            $cloud->isSuspended() => 'Suspended',
            $cloud->isPairing() => 'Pairing',
            $this->providerMode() === 'ark_mail' => 'ARK Mail',
            $this->providerMode() === 'local_log' => 'Local development mailer',
            default => 'Not configured',
        };
    }

    /**
     * @param  list<array{filename: string, mime: string, path?: string, content?: string}>  $attachments
     */
    public function sendMailable(
        TransactionalMailOperation $operation,
        string $recipientEmail,
        Mailable $mailable,
        string $idempotencyKey,
        ?string $domainObjectType = null,
        ?string $domainObjectId = null,
        array $attachments = [],
    ): TransactionalMailResult {
        $mode = $this->providerMode();

        if ($mode === 'none') {
            return TransactionalMailResult::notConfigured();
        }

        if ($mode === 'ark_mail') {
            $envelope = TransactionalMailEnvelope::fromMailable(
                $operation,
                $recipientEmail,
                $mailable,
                $idempotencyKey,
                $domainObjectType,
                $domainObjectId,
                $attachments,
            );

            return $this->arkMail->send($envelope);
        }

        // local_log / array — development and automated tests only
        try {
            Mail::to(strtolower(trim($recipientEmail)))->send($mailable);
        } catch (\Throwable $e) {
            return TransactionalMailResult::providerError($e->getMessage());
        }

        return TransactionalMailResult::sent();
    }

    public function ensureReadyOrResult(): ?TransactionalMailResult
    {
        if ($this->isReady()) {
            return null;
        }

        return TransactionalMailResult::notConfigured();
    }

    private function allowsLocalMailer(): bool
    {
        if (app()->environment('production')) {
            return false;
        }

        $mailer = (string) config('mail.default');

        return in_array($mailer, ['log', 'array'], true);
    }
}
