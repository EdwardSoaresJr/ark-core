@php
    $identity = is_array($thread['identity'] ?? null) ? $thread['identity'] : null;
    $phoneDigits = preg_replace('/\D+/', '', (string) ($identity['phone'] ?? ''));
    $showComposer = filled($thread['composer'] ?? null)
        && ($section ?? '') !== 'history'
        && in_array($selected['kind'] ?? '', ['conversation', 'call'], true);
    $listFilter = $listFilter ?? 'needs';
    $ownerFilter = $ownerFilter ?? 'everyone';
    $events = $thread['events'] ?? [];
    $lastDate = null;
    $backHref = match ($section ?? '') {
        'history' => route('operations.communications.history', request()->only(['q', 'from', 'to', 'media', 'page'])),
        'inbox' => route('operations.communications.inbox', array_filter([
            'filter' => $listFilter,
            'owner' => $ownerFilter !== 'everyone' ? $ownerFilter : null,
        ])),
        default => null,
    };
    $laneBadge = static fn (?string $lane): string => match ($lane) {
        'waiting' => 'bg-yellow-lt',
        'follow' => 'bg-azure-lt',
        'resolved' => 'bg-green-lt',
        default => 'bg-red-lt',
    };
@endphp

<div class="card chat flex-fill overflow-hidden" data-comms-thread-card>
    <div class="card-header d-block py-2" data-comms-thread-identity>
        @if ($backHref !== null)
            <a class="btn btn-sm btn-ghost-secondary d-lg-none mb-1 ps-0" href="{{ $backHref }}">
                <x-tabler.icon name="arrow-left" />
                Back to list
            </a>
        @endif
        @if ($identity !== null)
            <div class="d-flex flex-wrap align-items-center gap-2">
                <h3 class="card-title mb-0">{{ $identity['name'] ?? $thread['title'] ?? 'Unknown' }}</h3>
                @if (filled($identity['lane_label'] ?? null))
                    <span class="badge {{ $laneBadge($identity['lane'] ?? 'needs') }}">{{ $identity['lane_label'] }}</span>
                @endif
                @if (filled($identity['link_status'] ?? $identity['customer_status'] ?? null))
                    <span class="text-secondary small">{{ $identity['link_status'] ?? $identity['customer_status'] }}</span>
                @endif
                <button
                    type="button"
                    class="btn btn-sm ms-auto d-xl-none"
                    data-comms-context-toggle
                    :aria-pressed="contextOpen.toString()"
                    @click="contextOpen = ! contextOpen"
                >
                    <x-tabler.icon name="user" />
                    Customer
                </button>
            </div>
            <div class="d-flex flex-wrap column-gap-3 small mt-1" data-comms-thread-contact>
                @if ($phoneDigits !== '')
                    <a href="tel:{{ $phoneDigits }}">{{ $identity['phone'] }}</a>
                @endif
                @if (filled($identity['email'] ?? null))
                    <a href="mailto:{{ $identity['email'] }}" class="text-truncate">{{ $identity['email'] }}</a>
                @endif
                @if (filled($identity['last_interaction_label'] ?? null))
                    <span class="text-secondary ms-md-auto">{{ $identity['last_interaction_label'] }}</span>
                @endif
            </div>
            <div class="d-flex flex-wrap column-gap-3 small text-secondary" data-comms-thread-visit>
                @if (filled($identity['ro_url'] ?? null))
                    <a href="{{ $identity['ro_url'] }}">{{ $identity['ro_label'] ?? 'Repair order' }}</a>
                @elseif (filled($identity['ro_label'] ?? null))
                    <span>{{ $identity['ro_label'] }}</span>
                @endif
                @if (filled($identity['vehicle_label'] ?? null))
                    <span>{{ $identity['vehicle_label'] }}</span>
                @endif
                @if (filled($identity['ro_status'] ?? null))
                    <span>{{ $identity['ro_status'] }}</span>
                @endif
            </div>
        @else
            <h3 class="card-title mb-0">Select a conversation</h3>
        @endif
    </div>

    @if (is_array($thread['decision'] ?? null))
        @include('operations.communications.workspace.partials.decision-panel', ['decision' => $thread['decision']])
    @endif

    <div
        id="comms-workspace-thread-messages"
        class="card-body chat-bubbles gap-2 overflow-auto flex-fill p-2 p-md-3"
        data-comms-thread-body
        aria-label="Conversation"
        @if (($section ?? '') !== 'history' && ($selected['kind'] ?? '') === 'conversation' && filled($identity['mark_read_url'] ?? null))
            data-mark-read-url="{{ $identity['mark_read_url'] }}"
            data-conversation-key="{{ $selected['key'] ?? '' }}"
        @endif
    >
        @if ($thread === null)
            <div class="text-secondary text-center my-auto">Select a conversation</div>
        @else
            @forelse ($events as $event)
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
                    <div class="text-center text-secondary small mt-1">{{ $occurred->timezone(config('app.display_timezone'))->format('D, M j, Y') }}</div>
                @endif
                @if (is_array($event) && ($event['kind'] ?? '') === 'website_request')
                    @include('operations.communications.workspace.partials.website-request-card', ['event' => $event])
                @elseif (is_array($event) && filled($event['call_session_id'] ?? null))
                    @include('operations.communications.workspace.partials.call-event-card', ['event' => $event])
                @elseif ($event instanceof \App\Ark\Operations\Timeline\OperationalEventEntry)
                    {{-- operations.timeline.partials.event-bubble belongs to another workstream and stays legacy. --}}
                    <div class="ark-legacy bg-white text-dark rounded">
                        @include('operations.timeline.partials.event-bubble', ['event' => $event])
                    </div>
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
            @empty
                <div class="text-secondary text-center my-auto">No messages yet.</div>
            @endforelse
        @endif
    </div>

    @if ($showComposer)
        @include('operations.communications.workspace.partials.composer-panel', [
            'composer' => $thread['composer'],
            'section' => $section,
            'thread' => $thread,
        ])
    @else
        <footer class="card-footer text-secondary small" data-comms-composer-idle>
            {{ $thread === null ? 'Select a conversation to reply' : 'Reply is not available' }}
        </footer>
    @endif

    <div class="position-fixed top-0 start-0 w-100 h-100 bg-dark bg-opacity-25 z-3 d-none" data-comms-switch aria-hidden="true">
        <div class="d-flex h-100 align-items-center justify-content-center">
            <div class="card card-sm shadow">
                <div class="card-body d-flex align-items-center gap-2">
                    <span class="spinner-border text-secondary"></span>
                </div>
            </div>
        </div>
    </div>
</div>
