@php
    /** @var \App\Ark\Operations\Timeline\OperationalEventEntry $event */
    use App\Ark\Operations\Conversations\ConversationMessage;
    use App\Ark\Operations\Telephony\CallSession;

    $message = $event->subject instanceof ConversationMessage ? $event->subject : null;
    $body = filled($event->body) && $event->body !== '(attachment)' ? $event->body : null;
    $channelLabel = $event->metadata['channel_label'] ?? '';
    if ($message !== null
        && $message->relationLoaded('attachments')
        && $message->attachments->isNotEmpty()
        && $channelLabel === 'SMS') {
        $channelLabel = 'MMS';
    }
    $occurredLabel = $event->occurredAt->timezone(config('app.display_timezone'))->format('M j, g:i A');
    $evidenceBits = array_values(array_filter([
        $channelLabel !== '' ? $channelLabel : null,
        $occurredLabel,
        filled($event->actor) ? 'Actor: '.$event->actor : null,
        filled($event->source->value ?? null) ? 'Source: '.$event->source->value : null,
    ]));
@endphp

@if ($event->subject instanceof CallSession)
    @include('operations.timeline.partials.call-event-bubble', ['event' => $event])
@else
    @php
        $bubbleClass = match ($event->tone->value) {
            'shop' => 'ops-comms-workspace__bubble--outbound',
            'internal' => 'ops-comms-workspace__bubble--internal',
            'system' => 'ops-comms-workspace__bubble--internal',
            default => 'ops-comms-workspace__bubble--inbound',
        };
    @endphp

    <article @class(['ops-comms-workspace__bubble', $bubbleClass])>
        <p class="ops-comms-workspace__bubble-meta">
            {{ $event->headline }}
            · {{ $occurredLabel }}
        </p>
        @if (filled($body))
            <p class="ops-comms-workspace__bubble-body">{{ $body }}</p>
        @endif
        @if ($message !== null)
            @include('operations.conversations.partials.message-attachments', ['message' => $message])
        @endif
        @if ($evidenceBits !== [] || ($event->links ?? []) !== [])
            <details class="ops-comms-workspace__evidence">
                <summary>Evidence</summary>
                <ul class="ops-comms-workspace__evidence-list">
                    @foreach ($evidenceBits as $bit)
                        <li>{{ $bit }}</li>
                    @endforeach
                    @foreach ($event->links as $linkLabel => $linkUrl)
                        <li>
                            <a href="{{ $linkUrl }}" class="ops-page-link">{{ is_string($linkLabel) ? $linkLabel : 'Open' }}</a>
                        </li>
                    @endforeach
                </ul>
            </details>
        @endif
    </article>
@endif
