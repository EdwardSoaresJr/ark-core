<?php

namespace App\Ark\Operations\Realtime;

use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionDirection;
use App\Ark\Operations\Telephony\CallSessionStatus;
use App\Ark\Operations\Telephony\TelephonyProviderType;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Authority entry point: append SessionEvent, then derive CallSession current truth.
 */
final class RecordSessionEventAction
{
    public function __construct(
        private readonly ApplySessionEventToCallSessionAction $applyToCallSession,
    ) {}

    /**
     * Open a new realtime session shell and record SessionStarted.
     *
     * @param  array{
     *     direction?: CallSessionDirection,
     *     from_number?: string,
     *     to_number?: string,
     *     normalized_from?: string,
     *     normalized_to?: string|null,
     *     customer_id?: int|null,
     *     repair_order_id?: int|null,
     *     provider_call_sid?: string|null,
     * }  $identity
     * @return array{session: CallSession, event: SessionEvent}
     */
    public function begin(array $identity, ?User $actor = null, ?Carbon $occurredAt = null): array
    {
        $occurredAt ??= now();

        $provider = $identity['provider'] ?? TelephonyProviderType::Fake;
        if (! $provider instanceof TelephonyProviderType) {
            $provider = TelephonyProviderType::from((string) $provider);
        }

        $providerCallSid = (string) ($identity['provider_call_sid'] ?? 'fake_'.Str::uuid());

        $session = CallSession::query()
            ->where('provider', $provider)
            ->where('provider_call_sid', $providerCallSid)
            ->first();

        if ($session === null) {
            try {
                $session = CallSession::query()->create([
                    'provider' => $provider,
                    'provider_call_sid' => $providerCallSid,
                    'direction' => $identity['direction'] ?? CallSessionDirection::Inbound,
                    'from_number' => $identity['from_number'] ?? '7195550100',
                    'to_number' => $identity['to_number'] ?? '7195551000',
                    'normalized_from' => $identity['normalized_from'] ?? '7195550100',
                    'normalized_to' => $identity['normalized_to'] ?? '7195551000',
                    'status' => CallSessionStatus::Ringing,
                    'customer_id' => $identity['customer_id'] ?? null,
                    'repair_order_id' => $identity['repair_order_id'] ?? null,
                    'started_at' => $occurredAt,
                    'raw_payload' => $identity['raw_payload'] ?? ['source' => 'session_event'],
                ]);
            } catch (UniqueConstraintViolationException) {
                $session = CallSession::query()
                    ->where('provider', $provider)
                    ->where('provider_call_sid', $providerCallSid)
                    ->firstOrFail();
            }
        }

        $started = SessionEvent::query()
            ->where('call_session_id', $session->id)
            ->where('event_type', SessionEventType::SessionStarted)
            ->first();

        if ($started !== null) {
            return ['session' => $session->fresh(), 'event' => $started];
        }

        $event = $this->record($session, SessionEventType::SessionStarted, [], $actor, $occurredAt);

        return ['session' => $session->fresh(), 'event' => $event];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function record(
        CallSession $session,
        SessionEventType $type,
        array $payload = [],
        ?User $actor = null,
        ?Carbon $occurredAt = null,
    ): SessionEvent {
        $occurredAt ??= now();

        $event = SessionEvent::query()->create([
            'call_session_id' => $session->id,
            'event_type' => $type,
            'payload' => $payload !== [] ? $payload : null,
            'actor_user_id' => $actor?->id,
            'occurred_at' => $occurredAt,
        ]);

        $this->applyToCallSession->apply($session, $type, $payload, $occurredAt);

        return $event;
    }
}
