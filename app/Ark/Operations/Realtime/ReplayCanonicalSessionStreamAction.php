<?php

namespace App\Ark\Operations\Realtime;

use App\Ark\Operations\Telephony\CallSession;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Replays a canonical stream through RecordSessionEventAction — used for parity tests.
 */
final class ReplayCanonicalSessionStreamAction
{
    public function __construct(
        private readonly RecordSessionEventAction $recordSessionEvent,
    ) {}

    /**
     * @param  array<string, mixed>  $identityOverrides
     */
    public function replay(CanonicalSessionStream $stream, array $identityOverrides = [], ?User $actor = null): CallSession
    {
        $session = null;
        $startedAt = Carbon::parse('2026-06-29 10:00:00');

        foreach ($stream->events as $index => $event) {
            $occurredAt = $startedAt->copy()->addSeconds($index * 2);

            if ($event->type === SessionEventType::SessionStarted) {
                $identity = array_merge($event->sessionIdentity ?? [], $identityOverrides);
                ['session' => $session] = $this->recordSessionEvent->begin($identity, $actor, $occurredAt);

                continue;
            }

            if ($session === null) {
                throw new \RuntimeException('Canonical stream must begin with session_started.');
            }

            $this->recordSessionEvent->record(
                $session,
                $event->type,
                $event->payload,
                $actor,
                $occurredAt,
            );
        }

        if ($session === null) {
            throw new \RuntimeException('Canonical stream produced no session.');
        }

        return $session->fresh(['sessionEvents']);
    }
}
