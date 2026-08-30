<?php

namespace App\Ark\Mail;

use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;

/**
 * Official outbound transactional email entry.
 *
 * Production: ARK Mail only (via ArkMailClient → private ark-mail).
 * Local / CI / testing: Laravel log|array mailers permitted.
 *
 * No official BYO Postmark/SMTP path. Forks may add providers under AGPL;
 * official ARK does not maintain that seam.
 */
final class OutboundTransactionalMail
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
        $settings = ShopSettings::current();

        return match (true) {
            $settings->ark_mail_status === 'suspended' => 'Suspended',
            $settings->ark_mail_status === 'error' => 'Configuration error',
            $this->arkMail->isConfigured() => 'Connected',
            $this->providerMode() === 'local_log' => 'Local development mailer',
            default => 'Not connected',
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
