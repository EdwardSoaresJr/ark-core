@props(['message', 'ui' => 'tabler'])

@php
    use App\Ark\Operations\Communications\OperationalCommunicationChannel;

    $hasAttachments = $message->relationLoaded('attachments')
        ? $message->attachments->isNotEmpty()
        : false;

    [$typePill, $typePillClass, $typeBadgeClass] = match (true) {
        $message->channel === OperationalCommunicationChannel::Sms && $hasAttachments => [
            'MMS',
            'border-emerald-200 bg-emerald-50 text-emerald-900',
            'bg-green-lt',
        ],
        $message->channel === OperationalCommunicationChannel::Sms => [
            'Text',
            'border-emerald-200 bg-emerald-50 text-emerald-900',
            'bg-green-lt',
        ],
        $message->channel === OperationalCommunicationChannel::Email => [
            'Email',
            'border-violet-200 bg-violet-50 text-violet-900',
            'bg-purple-lt',
        ],
        $message->channel === OperationalCommunicationChannel::Messenger => [
            'Messenger',
            'border-sky-200 bg-sky-50 text-sky-900',
            'bg-azure-lt',
        ],
        $message->channel === OperationalCommunicationChannel::Website && ($message->metadata['portal_estimate_view'] ?? false) => [
            'Estimate',
            'border-sky-200 bg-sky-50 text-sky-900',
            'bg-azure-lt',
        ],
        $message->channel === OperationalCommunicationChannel::Website => [
            'Portal',
            'border-indigo-200 bg-indigo-50 text-indigo-900',
            'bg-indigo-lt',
        ],
        default => [
            $message->channel->label(),
            'border-amber-200 bg-amber-50 text-amber-900',
            'bg-yellow-lt',
        ],
    };
    $occurredLabel = $message->occurred_at?->timezone(config('app.display_timezone'))->format('M j, g:i A');
@endphp

@if ($ui === 'legacy')
    <div {{ $attributes->class(['px-3 py-2.5 text-xs leading-5 text-slate-700']) }} data-conversation-message-id="{{ $message->id }}">
        <div class="mb-1 flex flex-wrap items-center gap-2">
            <span class="ops-state-pill {{ $typePillClass }}">{{ $typePill }}</span>
            <span class="text-[11px] font-semibold text-slate-400">
                {{ $occurredLabel }}
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
@else
    {{-- Server-rendered messages (ConversationMessageRenderer) pass legacy Tailwind classes; this row sets its own. --}}
    <div {{ $attributes->except('class') }} class="px-3 py-2 border-bottom" data-conversation-message-id="{{ $message->id }}">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
            <span class="badge {{ $typeBadgeClass }}">{{ $typePill }}</span>
            <span class="text-secondary small">{{ $occurredLabel }}</span>
        </div>
        <div class="fw-bold">
            {{ $message->participant->displayLabel() }}
            <span class="fw-normal text-secondary">· {{ $message->direction->label() }}</span>
        </div>
        @if (filled($message->body) && $message->body !== '(attachment)')
            <p class="mt-1 mb-0">{{ $message->body }}</p>
        @endif
        @include('operations.conversations.partials.message-attachments', ['message' => $message, 'mediaUi' => 'tabler'])
    </div>
@endif
