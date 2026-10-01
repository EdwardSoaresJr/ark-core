<?php

namespace App\Ark\Mail;

/**
 * Payload for a Platform email intent. Core supplies facts. Platform owns the template.
 *
 * @param  array<string, mixed>  $variables
 * @param  list<array<string, mixed>>  $attachments
 * @param  array<string, mixed>  $metadata
 */
final class EmailIntent
{
    /**
     * @param  array<string, mixed>  $variables
     * @param  list<array<string, mixed>>  $attachments
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    public static function payload(
        string $operation,
        string $to,
        array $variables,
        string $idempotencyKey,
        ?string $domainObjectType = null,
        ?string $domainObjectId = null,
        array $attachments = [],
        array $metadata = [],
    ): array {
        $payload = [
            'operation' => $operation,
            'to' => $to,
            'variables' => $variables,
            'idempotency_key' => $idempotencyKey,
        ];

        if ($domainObjectType !== null) {
            $payload['domain_object_type'] = $domainObjectType;
        }

        if ($domainObjectId !== null) {
            $payload['domain_object_id'] = $domainObjectId;
        }

        if ($attachments !== []) {
            $payload['attachments'] = $attachments;
        }

        if ($metadata !== []) {
            $payload['metadata'] = $metadata;
        }

        return $payload;
    }
}
