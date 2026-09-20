<?php

namespace App\Ark\Mail;

use Illuminate\Mail\Mailable;

/**
 * @param  list<array{filename: string, mime: string, path?: string, content?: string}>  $attachments
 */
final class TransactionalMailEnvelope
{
    /**
     * @param  list<array{filename: string, mime: string, path?: string, content?: string}>  $attachments
     */
    public function __construct(
        public readonly TransactionalMailOperation $operation,
        public readonly string $recipientEmail,
        public readonly string $subject,
        public readonly string $htmlBody,
        public readonly ?string $textBody,
        public readonly string $idempotencyKey,
        public readonly ?string $domainObjectType = null,
        public readonly ?string $domainObjectId = null,
        public readonly array $attachments = [],
        public readonly ?string $correlationId = null,
    ) {}

    public static function fromMailable(
        TransactionalMailOperation $operation,
        string $recipientEmail,
        Mailable $mailable,
        string $idempotencyKey,
        ?string $domainObjectType = null,
        ?string $domainObjectId = null,
        array $attachments = [],
    ): self {
        $rendered = $mailable->render();
        $subject = (string) ($mailable->subject ?? 'Message from your shop');

        return new self(
            operation: $operation,
            recipientEmail: strtolower(trim($recipientEmail)),
            subject: $subject,
            htmlBody: $rendered,
            textBody: strip_tags($rendered),
            idempotencyKey: $idempotencyKey,
            domainObjectType: $domainObjectType,
            domainObjectId: $domainObjectId,
            attachments: $attachments,
        );
    }
}
