<?php

namespace App\Ark\Operations\Timeline\Mappers;

use App\Ark\Operations\Realtime\SessionEvent;
use App\Ark\Operations\Realtime\SessionEventType;
use App\Ark\Operations\Timeline\OperationalEventEntry;
use App\Ark\Operations\Timeline\OperationalEventKind;
use App\Ark\Operations\Timeline\OperationalEventSource;
use App\Ark\Operations\Timeline\OperationalEventTone;

final class SessionEventTimelineMapper
{
    public function map(SessionEvent $event): OperationalEventEntry
    {
        $event->loadMissing(['actor:id,name', 'callSession.owner:id,name']);

        $type = $event->event_type;
        $payload = $event->payload ?? [];

        $body = match ($type) {
            SessionEventType::SessionTransferred => $this->transferBody($payload),
            SessionEventType::SessionHeld => 'On hold',
            default => null,
        };

        return new OperationalEventEntry(
            source: OperationalEventSource::SessionEvent,
            kind: OperationalEventKind::SessionActivity,
            occurredAt: $event->occurred_at,
            headline: $type->timelineHeadline(),
            body: $body,
            actor: $event->actor?->name ?? $event->callSession?->owner?->name,
            tone: OperationalEventTone::Shop,
            links: [],
            metadata: [
                'hub_filter' => 'call',
                'timeline_category' => 'session_activity',
                'call_session_id' => $event->call_session_id,
                'session_event_id' => $event->id,
                'session_event_type' => $type->value,
            ],
            subject: $event,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function transferBody(array $payload): string
    {
        $from = trim((string) ($payload['from_user_name'] ?? ''));
        $to = trim((string) ($payload['to_user_name'] ?? ''));

        if ($from !== '' && $to !== '') {
            return "From {$from} to {$to}";
        }

        return 'Ownership changed';
    }
}
