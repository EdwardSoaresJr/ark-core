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
    $delivery = isset($delivery) && is_array($delivery) ? $delivery : null;
    $notes = isset($notes) && is_array($notes) ? $notes : [];
    $subject = trim((string) (($message?->metadata ?? [])['subject'] ?? ''));
    $tone = $event->tone->value;
    $attachmentCount = $message !== null && $message->relationLoaded('attachments') ? $message->attachments->count() : 0;
    $kindLabel = match (true) {
        in_array($tone, ['internal', 'system'], true) => 'Internal note',
        $channelLabel === 'MMS' => 'Picture message',
        $channelLabel === 'SMS' => 'Text message',
        $channelLabel !== '' => $channelLabel,
        default => null,
    };
    $detailLines = array_values(array_filter([
        $kindLabel !== null && $attachmentCount > 0 ? $kindLabel.' with '.$attachmentCount.' '.str('attachment')->plural($attachmentCount) : $kindLabel,
        match (true) {
            in_array($tone, ['internal', 'system'], true) => 'Added '.$occurredLabel.(filled($event->actor) ? ' by '.$event->actor : ''),
            $tone === 'shop' => 'Sent '.$occurredLabel.(filled($event->actor) ? ' by '.$event->actor : ''),
            default => 'Received '.$occurredLabel.(filled($event->actor) ? ' from '.$event->actor : ''),
        },
        filled($event->metadata['visit_label'] ?? null)
            ? 'About '.$event->metadata['visit_label'].(filled($event->metadata['visit_vehicle_label'] ?? null) ? ' · '.$event->metadata['visit_vehicle_label'] : '')
            : null,
        filled($event->metadata['imported_label'] ?? null) ? 'Imported from earlier records' : null,
    ]));
@endphp

@if ($event->subject instanceof CallSession)
    @include('operations.timeline.partials.call-event-bubble', ['event' => $event])
@else
    @php
        $direction = match ($event->tone->value) {
            'shop' => 'outbound',
            'internal', 'system' => 'internal',
            default => 'inbound',
        };
    @endphp

    <div
        @class([
            'd-flex',
            'justify-content-end' => $direction === 'outbound',
            'justify-content-center' => $direction === 'internal',
        ])
        @if (filled($event->metadata['platform_message_public_id'] ?? null))
            data-platform-message-id="{{ $event->metadata['platform_message_public_id'] }}"
        @endif
    >
        <div @class(['d-flex flex-column', 'align-items-end' => $direction === 'outbound']) style="max-width: 88%">
        <article
            @class([
                'chat-bubble px-3 py-2',
                'chat-bubble-me' => $direction === 'outbound',
                'bg-yellow-lt' => $direction === 'internal',
                'border border-danger' => $delivery['failed'] ?? false,
            ])
            data-comms-bubble="{{ $direction }}"
        >
            <div class="chat-bubble-title small text-secondary">
                @if ($channelLabel !== '' && $direction !== 'internal')
                    <span class="badge bg-secondary-lt me-1" data-comms-channel>{{ $channelLabel }}</span>
                @endif
                {{ $event->headline }}
                @if (filled($event->metadata['visit_label'] ?? null))
                    · {{ $event->metadata['visit_label'] }}
                    @if (filled($event->metadata['visit_vehicle_label'] ?? null))
                        · {{ $event->metadata['visit_vehicle_label'] }}
                    @endif
                @endif
                · {{ $occurredLabel }}
                @if (filled($event->metadata['imported_label'] ?? null))
                    · <span class="fw-medium">{{ $event->metadata['imported_label'] }}</span>
                @endif
            </div>
            <div class="chat-bubble-body">
                @if ($subject !== '')
                    <div class="fw-bold mb-1" data-comms-email-subject>{{ $subject }}</div>
                @endif
                @if (filled($body))
                    <p class="mb-0" style="white-space: pre-wrap">{{ $body }}</p>
                @endif
                @if ($message !== null)
                    @include('operations.conversations.partials.message-attachments', [
                        'message' => $message,
                        'mediaUi' => 'tabler',
                    ])
                @endif
                @include('operations.communications.workspace.partials.message-media', [
                    'attachments' => $event->metadata['attachments'] ?? [],
                    'mediaUi' => 'tabler',
                ])
                @if (filled($event->metadata['context_label'] ?? null))
                    <div class="mt-1 small text-secondary">{{ $event->metadata['context_label'] }}</div>
                @endif
                @if ($detailLines !== [] || ($event->links ?? []) !== [])
                    <details class="mt-1 small" data-comms-details>
                        <summary class="text-secondary">Details</summary>
                        <ul class="list-unstyled mb-0 mt-1">
                            @foreach ($detailLines as $bit)
                                <li>{{ $bit }}</li>
                            @endforeach
                            @foreach ($event->links as $linkLabel => $linkUrl)
                                <li>
                                    <a href="{{ $linkUrl }}">{{ is_string($linkLabel) ? $linkLabel : 'Open' }}</a>
                                </li>
                            @endforeach
                        </ul>
                    </details>
                @endif
            </div>
        </article>
        @if ($delivery !== null)
            <div @class(['small mt-1 px-1', 'text-danger' => $delivery['failed'], 'text-secondary' => ! $delivery['failed']]) data-comms-delivery="{{ $delivery['status'] }}">
                <x-tabler.icon :name="match (true) { $delivery['failed'] => 'circle-x', in_array($delivery['status'], ['delivered', 'read'], true) => 'circle-check', $delivery['status'] === 'sent' => 'check', default => 'clock' }" />
                {{ $delivery['label'] }}@if (filled($delivery['at_label'] ?? null)) · {{ $delivery['at_label'] }}@endif
                @if (filled($delivery['detail'] ?? null))
                    <div>{{ $delivery['detail'] }}</div>
                @endif
            </div>
        @endif
        @if ($notes !== [])
            <div class="d-flex flex-wrap gap-1 mt-1 px-1">
                @foreach ($notes as $note)
                    <span class="badge bg-{{ $note['tone'] }}-lt" @if (filled($note['title'])) title="{{ $note['title'] }}" @endif data-comms-message-note>{{ $note['label'] }}</span>
                @endforeach
            </div>
        @endif
        </div>
    </div>
@endif
