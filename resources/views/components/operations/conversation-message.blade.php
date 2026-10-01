@props(['message'])

@php
    use App\Ark\Operations\Communications\OperationalCommunicationChannel;

    $hasAttachments = $message->relationLoaded('attachments')
        ? $message->attachments->isNotEmpty()
        : false;

    [$typePill, $typePillClass] = match (true) {
        $message->channel === OperationalCommunicationChannel::Sms && $hasAttachments => [
            'MMS',
            'border-emerald-200 bg-emerald-50 text-emerald-900',
        ],
        $message->channel === OperationalCommunicationChannel::Sms => [
            'Text',
            'border-emerald-200 bg-emerald-50 text-emerald-900',
        ],
        $message->channel === OperationalCommunicationChannel::Email => [
            'Email',
            'border-violet-200 bg-violet-50 text-violet-900',
        ],
        $message->channel === OperationalCommunicationChannel::Messenger => [
            'Messenger',
            'border-sky-200 bg-sky-50 text-sky-900',
        ],
        $message->channel === OperationalCommunicationChannel::Website && ($message->metadata['portal_estimate_view'] ?? false) => [
            'Estimate',
            'border-sky-200 bg-sky-50 text-sky-900',
        ],
        $message->channel === OperationalCommunicationChannel::Website => [
            'Portal',
            'border-indigo-200 bg-indigo-50 text-indigo-900',
        ],
        default => [
            $message->channel->label(),
            'border-amber-200 bg-amber-50 text-amber-900',
        ],
    };
@endphp

<div {{ $attributes->class(['px-3 py-2.5 text-xs leading-5 text-slate-700']) }} data-conversation-message-id="{{ $message->id }}">
    <div class="mb-1 flex flex-wrap items-center gap-2">
        <span class="ops-state-pill {{ $typePillClass }}">{{ $typePill }}</span>
        <span class="text-[11px] font-semibold text-slate-400">
            {{ $message->occurred_at?->timezone(config('app.display_timezone'))->format('M j, g:i A') }}
        </span>
    </div>
    <p class="text-sm font-black text-slate-950">
        {{ $message->participant->displayLabel() }}
        <span class="font-semibold text-slate-500">· {{ $message->direction->label() }}</span>
    </p>
    @if (filled($message->body) && $message->body !== '(attachment)')
        <p class="mt-1 text-sm leading-5 text-slate-700">{{ $message->body }}</p>
    @endif
    @include('operations.conversations.partials.message-attachments', ['message' => $message])
</div>
