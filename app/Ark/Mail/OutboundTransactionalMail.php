<?php

namespace App\Ark\Mail;

use App\Ark\Mail\Providers\ArkMailProvider;
use App\Ark\Mail\Providers\NotConfiguredMailProvider;
use App\Ark\Operations\Settings\ShopIntegrationCredentials;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Resolves outbound path: ARK Mail (preferred when connected) → BYO Laravel/Postmark → not configured.
 */
final class OutboundTransactionalMail
{
    public function __construct(
        private readonly ArkMailProvider $arkMail,
        private readonly ShopIntegrationCredentials $credentials,
    ) {}

    public function providerMode(): string
    {
        if ($this->arkMail->isConfigured()) {
            return 'ark_mail';
        }

        if ($this->credentials->postmarkConfigured()) {
            return 'byo_postmark';
        }

        // Local/dev log mailer is not a production provider — still allow explicit log/array in non-production
        $mailer = (string) config('mail.default');
        if (app()->environment('production') && in_array($mailer, ['log', 'array'], true)) {
            return 'none';
        }

        if (in_array($mailer, ['postmark', 'smtp', 'ses', 'mailgun', 'sendmail'], true)) {
            return 'byo_laravel';
        }

        if (! app()->environment('production') && in_array($mailer, ['log', 'array'], true)) {
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
            $this->credentials->postmarkConfigured() => 'Connected (your Postmark)',
            $this->providerMode() === 'local_log' => 'Local log mailer',
            $this->providerMode() === 'byo_laravel' => 'Connected (your mailer)',
            default => 'Not connected',
        };
    }

    /**
     * Preferred entry for existing Mailable-based flows.
     *
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

        // BYO / local: preserve existing Laravel Mail behavior
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
}
