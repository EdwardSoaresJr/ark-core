@php
    use App\Ark\Operations\Conversations\ConversationMessage;
    use App\Ark\Operations\Telephony\CallSession;
    use App\Ark\Operations\Timeline\OperationalEventSource;

    /** @var \App\Ark\Operations\Timeline\OperationalEventEntry $event */
@endphp

@if ($event->source === OperationalEventSource::CallSession && $event->subject instanceof CallSession)
    <x-operations.customer-hub-comms-call-row :call-session="$event->subject" />
@elseif ($event->subject instanceof ConversationMessage)
    <x-operations.conversation-message :message="$event->subject" />
@else
    <div class="px-3 py-2 border-bottom">
        <div class="fw-bold">{{ $event->headline }}</div>
        @if (filled($event->body))
            <p class="mt-1 mb-0 text-secondary" style="white-space: pre-wrap">{{ $event->body }}</p>
        @endif
    </div>
@endif
