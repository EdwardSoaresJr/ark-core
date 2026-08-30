<?php

namespace App\Ark\Mail;

use App\Ark\Cloud\CloudConnection;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;

/**
 * Sends customer transactional email through the shop's configured provider.
 *
 * Selection is exclusive: ARK Mail, shop-owned Postmark, local log/array (non-production),
 * or not configured. No silent cross-provider fallback.
 */
final class OutboundTransactionalMail
{
    public const PROVIDER_ARK_MAIL = 'ark_mail';

    public const PROVIDER_POSTMARK = 'postmark';

    public const PROVIDER_NONE = 'none';

    public function __construct(
        private readonly ArkMailClient $arkMail,
    ) {}

    /**
     * @return 'ark_mail'|'byo_postmark'|'local_log'|'none'
     */
    public function providerMode(): string
    {
        $selected = $this->selectedProvider();

        if ($selected === self::PROVIDER_ARK_MAIL) {
            return $this->arkMail->isConfigured() ? 'ark_mail' : 'none';
        }

        if ($selected === self::PROVIDER_POSTMARK) {
            return $this->byoPostmarkConfigured() ? 'byo_postmark' : 'none';
        }

        // No explicit production provider — allow log/array only outside production (tests/dev).
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
        $selected = $this->selectedProvider();

        return match (true) {
            $cloud->isSuspended() && $selected === self::PROVIDER_ARK_MAIL => 'Suspended',
            $cloud->isPairing() && $selected === self::PROVIDER_ARK_MAIL => 'Pairing',
            $this->providerMode() === 'ark_mail' => 'ARK Mail',
            $this->providerMode() === 'byo_postmark' => 'Postmark (your account)',
            $this->providerMode() === 'local_log' => 'Local development mailer',
            $selected === self::PROVIDER_ARK_MAIL => 'ARK Mail (not connected)',
            $selected === self::PROVIDER_POSTMARK => 'Postmark (token required)',
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

        if ($mode === 'byo_postmark') {
            return $this->sendViaByoPostmark($recipientEmail, $mailable);
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

    /**
     * @return 'ark_mail'|'postmark'|'none'
     */
    public function selectedProvider(): string
    {
        $raw = strtolower(trim((string) (ShopSettings::current()->email_provider ?? '')));

        return match ($raw) {
            self::PROVIDER_ARK_MAIL => self::PROVIDER_ARK_MAIL,
            self::PROVIDER_POSTMARK => self::PROVIDER_POSTMARK,
            default => self::PROVIDER_NONE,
        };
    }

    public function byoPostmarkConfigured(): bool
    {
        return filled(ShopSettings::current()->postmark_token);
    }

    private function sendViaByoPostmark(string $recipientEmail, Mailable $mailable): TransactionalMailResult
    {
        $settings = ShopSettings::current();
        $token = (string) $settings->postmark_token;

        $previousDefault = config('mail.default');
        $previousToken = config('services.postmark.token');
        $previousStream = config('services.postmark.message_stream_id');

        config([
            'mail.default' => 'postmark',
            'services.postmark.token' => $token,
            'services.postmark.message_stream_id' => $settings->postmark_message_stream_id ?: null,
        ]);

        try {
            Mail::mailer('postmark')->to(strtolower(trim($recipientEmail)))->send($mailable);
        } catch (\Throwable $e) {
            return TransactionalMailResult::providerError('Postmark could not send the message.');
        } finally {
            config([
                'mail.default' => $previousDefault,
                'services.postmark.token' => $previousToken,
                'services.postmark.message_stream_id' => $previousStream,
            ]);
        }

        return TransactionalMailResult::sent();
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
