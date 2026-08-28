<?php

namespace App\Ark\Operations\Realtime;

use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionDirection;
use App\Ark\Operations\Telephony\CallSessionStatus;
use App\Ark\Operations\Telephony\IncomingCallPayload;
use App\Ark\Operations\Telephony\TelephonyProviderType;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Persists canonical SessionEvent DTOs — the only write path from normalized transport.
 */
final class IngestCanonicalSessionEventAction
{
    public function __construct(
        private readonly RecordSessionEventAction $recordSessionEvent,
    ) {}

    /**
     * @return array{0: CallSession, 1: bool} session and whether SessionStarted created a new row
     */
    public function ingest(
        IncomingCallPayload $payload,
        CanonicalSessionEvent $event,
        ?int $customerId = null,
        ?User $actor = null,
    ): array {
        $occurredAt = $event->occurredAt !== null
            ? Carbon::parse($event->occurredAt)
            : null;

        if ($event->type === SessionEventType::SessionStarted) {
            return $this->begin($payload, $event, $customerId, $actor, $occurredAt);
        }

        $session = $this->findSession($payload);

        if ($session === null) {
            $bootstrap = $this->bootstrapStarted($payload, $customerId);
            $session = $bootstrap['session'];
        }

        if (! $this->isDuplicate($session, $event)) {
            $this->recordSessionEvent->record(
                $session,
                $event->type,
                $event->payload,
                $actor,
                $occurredAt,
            );
        }

        $session = $this->refreshSession($session, $payload, $customerId);

        return [$session, false];
    }

    /**
     * @return array{0: CallSession, 1: bool}
     */
    private function begin(
        IncomingCallPayload $payload,
        CanonicalSessionEvent $event,
        ?int $customerId,
        ?User $actor,
        ?Carbon $occurredAt,
    ): array {
        $existing = $this->findSession($payload);

        if ($existing !== null) {
            $existing = $this->refreshSession($existing, $payload, $customerId);

            return [$existing, false];
        }

        $identity = $this->identityFromEvent($payload, $event);

        if ($customerId !== null) {
            $identity['customer_id'] = $customerId;
        }

        ['session' => $session] = $this->recordSessionEvent->begin($identity, $actor, $occurredAt);

        return [$session->fresh(), true];
    }

    /**
     * @return array{session: CallSession}
     */
    private function bootstrapStarted(IncomingCallPayload $payload, ?int $customerId): array
    {
        $identity = [
            'provider' => $payload->provider,
            'provider_call_sid' => $payload->providerCallSid,
            'direction' => $payload->direction,
            'from_number' => $payload->fromNumber,
            'to_number' => $payload->toNumber,
            'normalized_from' => $payload->normalizedFrom,
            'normalized_to' => $payload->normalizedTo,
            'raw_payload' => $payload->rawPayload,
        ];

        if ($customerId !== null) {
            $identity['customer_id'] = $customerId;
        }

        return $this->recordSessionEvent->begin($identity);
    }

    /**
     * @return array<string, mixed>
     */
    private function identityFromEvent(IncomingCallPayload $payload, CanonicalSessionEvent $event): array
    {
        $identity = $event->sessionIdentity ?? [];

        return array_merge([
            'provider' => $payload->provider,
            'provider_call_sid' => $payload->providerCallSid,
            'direction' => $payload->direction,
            'from_number' => $payload->fromNumber,
            'to_number' => $payload->toNumber,
            'normalized_from' => $payload->normalizedFrom,
            'normalized_to' => $payload->normalizedTo,
            'raw_payload' => $payload->rawPayload,
        ], $identity, [
            'raw_payload' => $payload->rawPayload,
        ]);
    }

    private function findSession(IncomingCallPayload $payload): ?CallSession
    {
        return CallSession::query()
            ->where('provider', $payload->provider)
            ->where('provider_call_sid', $payload->providerCallSid)
            ->first();
    }

    private function isDuplicate(CallSession $session, CanonicalSessionEvent $event): bool
    {
        return SessionEvent::query()
            ->where('call_session_id', $session->id)
            ->where('event_type', $event->type)
            ->exists();
    }

    private function refreshSession(CallSession $session, IncomingCallPayload $payload, ?int $customerId): CallSession
    {
        $dirty = false;

        if ($customerId !== null && $session->customer_id !== $customerId) {
            $session->customer_id = $customerId;
            $dirty = true;
        }

        $session->raw_payload = $payload->rawPayload;
        $dirty = true;

        if ($dirty) {
            $session->save();
        }

        return $session->fresh();
    }
}
