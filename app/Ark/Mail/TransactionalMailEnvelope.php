<?php

namespace App\Ark\Mail;

/**
 * @param  list<array{filename: string, mime: string, path?: string, content?: string}>  $attachments
 * @param  array<string, mixed>  $variables
 */
final class TransactionalMailEnvelope
{
    /**
     * @param  list<array{filename: string, mime: string, path?: string, content?: string}>  $attachments
     * @param  array<string, mixed>  $variables
     */
    public function __construct(
        public readonly TransactionalMailOperation $operation,
        public readonly string $recipientEmail,
        public readonly array $variables,
        public readonly string $idempotencyKey,
        public readonly ?string $domainObjectType = null,
        public readonly ?string $domainObjectId = null,
        public readonly array $attachments = [],
        public readonly ?string $correlationId = null,
    ) {}

    /**
     * @param  array<string, mixed>  $variables
     * @param  list<array{filename: string, mime: string, path?: string, content?: string}>  $attachments
     */
    public static function intent(
        TransactionalMailOperation $operation,
        string $recipientEmail,
        array $variables,
        string $idempotencyKey,
        ?string $domainObjectType = null,
        ?string $domainObjectId = null,
        array $attachments = [],
    ): self {
        return new self(
            operation: $operation,
            recipientEmail: strtolower(trim($recipientEmail)),
            variables: $variables,
            idempotencyKey: $idempotencyKey,
            domainObjectType: $domainObjectType,
            domainObjectId: $domainObjectId,
            attachments: $attachments,
        );
    }
}
