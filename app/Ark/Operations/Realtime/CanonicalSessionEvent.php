<?php

namespace App\Ark\Operations\Realtime;

/**
 * Provider-agnostic session event before persistence.
 * Normalizers produce this; RecordSessionEventAction persists it.
 */
final readonly class CanonicalSessionEvent
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>|null  $sessionIdentity  Required when type is SessionStarted
     */
    public function __construct(
        public SessionEventType $type,
        public array $payload = [],
        public ?string $occurredAt = null,
        public ?array $sessionIdentity = null,
    ) {}

    /**
     * Stable comparison shape for golden stream regression (ignores timestamps and ids).
     *
     * @return array{type: string, payload: array<string, mixed>}
     */
    public function signature(): array
    {
        return [
            'type' => $this->type->value,
            'payload' => $this->signaturePayload(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function signaturePayload(): array
    {
        if ($this->payload === []) {
            return [];
        }

        $payload = $this->payload;

        unset($payload['from_user_id'], $payload['to_user_id']);

        return $payload;
    }
}
