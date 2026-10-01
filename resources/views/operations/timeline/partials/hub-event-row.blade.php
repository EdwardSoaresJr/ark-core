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
    <div class="px-3 py-2 text-xs leading-4 text-slate-700">
        <p class="font-bold text-slate-950">{{ $event->headline }}</p>
        @if (filled($event->body))
            <p class="mt-0.5 whitespace-pre-wrap text-slate-600">{{ $event->body }}</p>
        @endif
    </div>
@endif
