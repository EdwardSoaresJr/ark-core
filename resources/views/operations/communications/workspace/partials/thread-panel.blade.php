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
@endphp

<div class="ops-comms-workspace__panel ops-comms-workspace__panel--thread">
    <header class="ops-comms-inbox__identity">
        @if (($section ?? '') === 'inbox')
            <a class="ops-comms-inbox__back" href="{{ route('operations.communications.inbox', array_filter(['filter' => $listFilter, 'owner' => $ownerFilter !== 'everyone' ? $ownerFilter : null])) }}">Back to list</a>
        @endif
        @if ($identity !== null)
            <div class="ops-comms-inbox__identity-title">
                <h3 class="ops-comms-workspace__thread-title">{{ $identity['name'] ?? $thread['title'] ?? 'Unknown' }}</h3>
                @if (filled($identity['lane_label'] ?? null))
                    <span @class(['ops-comms-inbox__badge', 'ops-comms-inbox__badge--'.($identity['lane'] ?? 'needs')])>{{ $identity['lane_label'] }}</span>
                @endif
                @if (filled($identity['link_status'] ?? $identity['customer_status'] ?? null))
                    <span class="ops-comms-inbox__match">{{ $identity['link_status'] ?? $identity['customer_status'] }}</span>
                @endif
                <button type="button" class="ops-comms-inbox__context-toggle" @click="contextOpen = ! contextOpen">Customer</button>
            </div>
            <div class="ops-comms-inbox__identity-contact">
                @if ($phoneDigits !== '')
                    <a href="tel:{{ $phoneDigits }}">{{ $identity['phone'] }}</a>
                @endif
                @if (filled($identity['email'] ?? null))
                    <a href="mailto:{{ $identity['email'] }}">{{ $identity['email'] }}</a>
                @endif
                @if (filled($identity['last_interaction_label'] ?? null))
                    <span class="ops-comms-inbox__last">{{ $identity['last_interaction_label'] }}</span>
                @endif
            </div>
            <div class="ops-comms-inbox__identity-visit">
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
            <h3 class="ops-comms-workspace__thread-title">Select a conversation</h3>
        @endif
    </header>

    @if (is_array($thread['decision'] ?? null))
        @include('operations.communications.workspace.partials.decision-panel', ['decision' => $thread['decision']])
    @endif

    <div
        id="comms-workspace-thread-messages"
        class="ops-comms-workspace__thread-body"
        aria-label="Conversation"
        @if (($section ?? '') !== 'history' && ($selected['kind'] ?? '') === 'conversation' && filled($identity['mark_read_url'] ?? null))
            data-mark-read-url="{{ $identity['mark_read_url'] }}"
            data-conversation-key="{{ $selected['key'] ?? '' }}"
        @endif
    >
        @if ($thread === null)
            <p class="ops-comms-workspace__timeline-empty">Select a conversation</p>
        @else
            @forelse ($events as $event)
                @php
                    $occurred = $event instanceof \App\Ark\Operations\Timeline\OperationalEventEntry ? $event->occurredAt : null;
                    $dateKey = $occurred?->timezone(config('app.display_timezone'))->toDateString();
                    $showDate = $dateKey !== null && $dateKey !== $lastDate;
                    if ($dateKey !== null) {
                        $lastDate = $dateKey;
                    }
                @endphp
                @if ($showDate)
                    <p class="ops-comms-inbox__date">{{ $occurred->timezone(config('app.display_timezone'))->format('D, M j, Y') }}</p>
                @endif
                @if ($event instanceof \App\Ark\Operations\Timeline\OperationalEventEntry)
                    @include('operations.timeline.partials.event-bubble', ['event' => $event])
                @else
                    @php
                        $direction = $event['direction'] ?? 'inbound';
                        $bubbleClass = match ($direction) {
                            'outbound' => 'ops-comms-workspace__bubble--outbound',
                            'internal', 'system' => 'ops-comms-workspace__bubble--internal',
                            default => 'ops-comms-workspace__bubble--inbound',
                        };
                    @endphp
                    <article @class(['ops-comms-workspace__bubble', $bubbleClass])>
                        <p class="ops-comms-workspace__bubble-meta">
                            {{ $event['direction_label'] ?? 'Message' }}
                            @if (filled($event['channel_label'] ?? null)) · {{ $event['channel_label'] }} @endif
                            @if (filled($event['occurred_at_label'] ?? null)) · {{ $event['occurred_at_label'] }} @endif
                        </p>
                        @if (filled($event['body'] ?? null))
                            <p class="ops-comms-workspace__bubble-body">{{ $event['body'] }}</p>
                        @endif
                    </article>
                @endif
            @empty
                <p class="ops-comms-workspace__timeline-empty">No messages yet.</p>
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
        <footer class="ops-comms-workspace__composer ops-comms-workspace__composer--idle">
            <p>{{ $thread === null ? 'Select a conversation to reply' : 'Reply is not available' }}</p>
        </footer>
    @endif

    <div class="ops-comms-workspace__switch" aria-hidden="true">
        <span class="ops-comms-workspace__switch-spin"></span>
    </div>
</div>
