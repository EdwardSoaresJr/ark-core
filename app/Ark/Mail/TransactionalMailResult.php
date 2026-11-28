<?php

namespace App\Ark\Mail;

/**
 * Domain result for outbound transactional email. Never claim "sent" on failure/not configured.
 */
final class TransactionalMailResult
{
    public const STATUS_SENT = 'sent';

    public const STATUS_PROVIDER_SENT = 'provider_sent';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_TRANSPORT_ACCEPTED = 'transport_accepted';

    public const STATUS_FAILED = 'failed';

    public const STATUS_NOT_CONFIGURED = 'not_configured';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_PROVIDER_ERROR = 'provider_error';

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly string $status,
        public readonly ?string $reasonCode = null,
        public readonly ?string $message = null,
        public readonly ?string $correlationId = null,
        public readonly array $context = [],
    ) {}

    public function ok(): bool
    {
        return in_array($this->status, [
            self::STATUS_SENT,
            self::STATUS_PROVIDER_SENT,
            self::STATUS_QUEUED,
            self::STATUS_TRANSPORT_ACCEPTED,
        ], true);
    }

    public function confirmedDelivered(): bool
    {
        // Platform may later map delivery webhooks here. SMTP acceptance never qualifies.
        return false;
    }

    public static function notConfigured(): self
    {
        return new self(
            self::STATUS_NOT_CONFIGURED,
            'email_not_configured',
            "Email isn't configured yet.",
        );
    }

    public static function sent(?string $correlationId = null): self
    {
        return new self(self::STATUS_SENT, null, null, $correlationId);
    }

    public static function providerSent(?string $correlationId = null, array $context = []): self
    {
        return new self(self::STATUS_PROVIDER_SENT, null, null, $correlationId, $context);
    }

    public static function queued(?string $correlationId = null, array $context = []): self
    {
        return new self(self::STATUS_QUEUED, null, 'Email queued for delivery.', $correlationId, $context);
    }

    public static function transportAccepted(?string $correlationId = null, array $context = []): self
    {
        return new self(
            self::STATUS_TRANSPORT_ACCEPTED,
            null,
            'Email accepted by the mail server. Delivery to the recipient is not confirmed.',
            $correlationId,
            $context,
        );
    }

    public static function failed(string $message, ?string $correlationId = null): self
    {
        return new self(self::STATUS_FAILED, 'transport_failed', $message, $correlationId);
    }

    public static function rejected(string $reasonCode, string $message, ?string $correlationId = null): self
    {
        return new self(self::STATUS_REJECTED, $reasonCode, $message, $correlationId);
    }

    public static function providerError(string $message, ?string $correlationId = null): self
    {
        return new self(self::STATUS_PROVIDER_ERROR, 'provider_unavailable', $message, $correlationId);
    }

    public function operatorMessage(): string
    {
        return match ($this->status) {
            self::STATUS_NOT_CONFIGURED => "Email isn't configured yet. Configure Email in Settings.",
            self::STATUS_REJECTED => $this->message ?? 'Email was rejected.',
            self::STATUS_PROVIDER_ERROR, self::STATUS_FAILED => $this->message ?? 'Email could not be sent.',
            self::STATUS_QUEUED => $this->message ?? 'Email queued for delivery.',
            self::STATUS_TRANSPORT_ACCEPTED => $this->message
                ?? 'Email accepted by the mail server. Delivery to the recipient is not confirmed.',
            self::STATUS_PROVIDER_SENT, self::STATUS_SENT => 'Email handed off to the mail provider.',
            default => $this->message ?? 'Email could not be sent.',
        };
    }
}
