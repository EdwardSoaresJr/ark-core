@php
    $eventDeliveries = $eventDeliveries ?? [];
    $eventNotes = $eventNotes ?? [];
    $eventKeys = $eventKeys ?? [];
    $lastDate = null;
@endphp
@foreach ($events as $event)
    @php
        $occurred = $event instanceof \App\Ark\Operations\Timeline\OperationalEventEntry
            ? $event->occurredAt
            : null;
        if ($occurred === null && is_array($event) && filled($event['occurred_at'] ?? null)) {
            try {
                $occurred = \Illuminate\Support\Carbon::parse($event['occurred_at']);
            } catch (\Throwable) {
                $occurred = null;
            }
        }
        $dateKey = $occurred?->timezone(config('app.display_timezone'))->toDateString();
        $showDate = $dateKey !== null && $dateKey !== $lastDate;
        if ($dateKey !== null) {
            $lastDate = $dateKey;
        }
    @endphp
    @if ($showDate)
        <div class="text-center text-secondary small mt-1" data-comms-date="{{ $dateKey }}">{{ $occurred->timezone(config('app.display_timezone'))->format('D, M j, Y') }}</div>
    @endif
    <div data-comms-entry="{{ $eventKeys[$loop->index] ?? '' }}">
    @if (is_array($event) && ($event['kind'] ?? '') === 'website_request')
        @include('operations.communications.workspace.partials.website-request-card', ['event' => $event])
    @elseif (is_array($event) && filled($event['call_session_id'] ?? null))
        @include('operations.communications.workspace.partials.call-event-card', ['event' => $event])
    @elseif ($event instanceof \App\Ark\Operations\Timeline\OperationalEventEntry)
        @include('operations.timeline.partials.event-bubble', [
            'event' => $event,
            'delivery' => $eventDeliveries[$loop->index] ?? null,
            'notes' => $eventNotes[$loop->index] ?? [],
        ])
    @else
        @php
            $direction = $event['direction'] ?? 'inbound';
        @endphp
        <div @class([
            'd-flex',
            'justify-content-end' => $direction === 'outbound',
            'justify-content-center' => in_array($direction, ['internal', 'system'], true),
        ])>
            <article
                @class([
                    'chat-bubble px-3 py-2',
                    'chat-bubble-me' => $direction === 'outbound',
                    'bg-yellow-lt' => in_array($direction, ['internal', 'system'], true),
                ])
                style="max-width: 88%"
                data-comms-bubble="{{ $direction }}"
            >
                <div class="chat-bubble-title small text-secondary">
                    {{ $event['direction_label'] ?? 'Message' }}
                    @if (filled($event['channel_label'] ?? null)) · {{ $event['channel_label'] }} @endif
                    @if (filled($event['occurred_at_label'] ?? null)) · {{ $event['occurred_at_label'] }} @endif
                </div>
                <div class="chat-bubble-body">
                    @if (filled($event['body'] ?? null) && ($event['body'] ?? '') !== '(attachment)')
                        <p class="mb-0" style="white-space: pre-wrap">{{ $event['body'] }}</p>
                    @endif
                    @include('operations.communications.workspace.partials.message-media', [
                        'attachments' => $event['attachments'] ?? [],
                        'mediaUi' => 'tabler',
                    ])
                    @if (filled($event['context_label'] ?? null))
                        <div class="mt-1 small text-secondary">{{ $event['context_label'] }}</div>
                    @endif
                </div>
            </article>
        </div>
    @endif
    </div>
@endforeach
