@php
    /** @var array<string, mixed>|null $thread */
    /** @var array<string, mixed>|null $selected */
    /** @var string $section */
    $identity = is_array($thread['identity'] ?? null) ? $thread['identity'] : null;
    $actions = is_array($identity['actions'] ?? null) ? $identity['actions'] : [];
    $known = (bool) ($identity['known_customer'] ?? false);
    $canMarkHandled = $section !== 'history'
        && (bool) ($identity['can_mark_handled'] ?? false)
        && filled($identity['mark_handled_url'] ?? null);
    $phoneDigits = preg_replace('/\D+/', '', (string) ($identity['phone'] ?? ''));
@endphp

<div class="ops-comms-workspace__panel ops-comms-workspace__panel--thread">
    @if ($thread === null)
        <div class="ops-comms-workspace__empty-state">
            <p class="ops-comms-workspace__empty-title">Select a customer</p>
            <p class="ops-comms-workspace__empty">Choose a relationship to see context, next actions, and conversation.</p>
        </div>
    @else
        <header class="ops-comms-workspace__thread-header ops-comms-workspace__identity ops-comms-workspace__control-center">
            @if ($identity !== null)
                <div class="ops-comms-workspace__identity-row">
                    <div class="ops-comms-workspace__identity-primary">
                        <h3 class="ops-comms-workspace__thread-title">{{ $identity['name'] ?? $thread['title'] ?? 'Conversation' }}</h3>
                        <span class="ops-comms-workspace__identity-inline ops-comms-workspace__identity-inline--phone">
                            @if ($phoneDigits !== '')
                                <a href="tel:{{ $phoneDigits }}" class="ops-comms-workspace__identity-link">{{ $identity['phone'] }}</a>
                            @else
                                No phone
                            @endif
                        </span>
                        @if (filled($identity['email'] ?? null))
                            <span class="ops-comms-workspace__identity-inline">
                                <a href="mailto:{{ $identity['email'] }}" class="ops-comms-workspace__identity-link">{{ $identity['email'] }}</a>
                            </span>
                        @endif
                        @if (filled($identity['location'] ?? null))
                            <span class="ops-comms-workspace__identity-inline">{{ $identity['location'] }}</span>
                        @endif
                        @if (! $known)
                            <span class="ops-comms-workspace__identity-unknown">{{ $identity['link_status'] ?? 'No customer' }}</span>
                        @endif
                    </div>
                    <div class="ops-comms-workspace__identity-side">
                        @if (filled($identity['origin_label'] ?? null))
                            <span class="ops-comms-workspace__chip ops-comms-workspace__chip--origin">{{ $identity['origin_label'] }}</span>
                        @endif
                        @if (filled($identity['turn_label'] ?? null))
                            <span class="ops-comms-workspace__chip">{{ $identity['turn_label'] }}</span>
                        @endif
                        @if ($canMarkHandled)
                            <form method="POST" action="{{ $identity['mark_handled_url'] }}" class="ops-comms-workspace__done-form">
                                @csrf
                                <input type="hidden" name="section" value="{{ $section }}">
                                <button type="submit" class="ops-comms-workspace__done-button" title="Clear from Needs attention">
                                    Mark handled
                                </button>
                            </form>
                        @endif
                    </div>
                </div>

                <div class="ops-comms-workspace__identity-row ops-comms-workspace__identity-row--meta">
                    <div class="ops-comms-workspace__identity-primary">
                        @if (filled($identity['vehicle_label'] ?? null))
                            <span class="ops-comms-workspace__identity-inline ops-comms-workspace__identity-inline--strong">{{ $identity['vehicle_label'] }}</span>
                        @endif
                        @if (filled($identity['ro_label'] ?? null))
                            <span class="ops-comms-workspace__identity-inline">
                                @if (filled($identity['ro_url'] ?? null))
                                    <a href="{{ $identity['ro_url'] }}" class="ops-page-link">{{ $identity['ro_label'] }}</a>
                                @else
                                    {{ $identity['ro_label'] }}
                                @endif
                            </span>
                        @endif
                        @if (filled($identity['ro_status'] ?? null))
                            <span class="ops-comms-workspace__identity-inline">{{ $identity['ro_status'] }}</span>
                        @endif
                        @if (filled($identity['last_activity'] ?? null))
                            <span class="ops-comms-workspace__list-meta">{{ $identity['last_activity'] }}</span>
                        @endif
                        @if (filled($identity['assigned'] ?? null))
                            <span class="ops-comms-workspace__list-meta">{{ $identity['assigned'] }}</span>
                        @endif
                    </div>
                    @if ($actions !== [])
                        <div class="ops-comms-workspace__control-actions" aria-label="Next Actions">
                            @foreach ($actions as $action)
                                @php
                                    $enabled = (bool) ($action['enabled'] ?? true);
                                    $label = (string) ($action['label'] ?? 'Action');
                                @endphp
                                @if ($enabled && filled($action['url'] ?? null))
                                    <a
                                        href="{{ $action['url'] }}"
                                        class="ops-comms-workspace__control-action"
                                        @if (filled($action['target'] ?? null)) target="{{ $action['target'] }}" rel="noopener" @endif
                                    >{{ $label }}</a>
                                @else
                                    <span class="ops-comms-workspace__control-action ops-comms-workspace__control-action--disabled" title="Unavailable">{{ $label }}</span>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>
            @else
                <h3 class="ops-comms-workspace__thread-title">
                    {{ $thread['title'] ?? 'Conversation' }}
                    @if (filled($thread['subtitle'] ?? null))
                        <span class="ops-comms-workspace__thread-subtitle">{{ $thread['subtitle'] }}</span>
                    @endif
                </h3>
                <div class="ops-comms-workspace__thread-meta">
                    @if (filled($thread['status_label'] ?? null))
                        <span class="ops-comms-workspace__chip">{{ $thread['status_label'] }}</span>
                    @endif
                    @if (filled($thread['assignment_label'] ?? null))
                        <span class="ops-comms-workspace__list-meta">{{ $thread['assignment_label'] }}</span>
                    @endif
                </div>
            @endif
        </header>

        <div
            id="comms-workspace-thread-messages"
            class="ops-comms-workspace__thread-body"
            aria-label="Conversation"
            @if ($section !== 'history' && ($selected['kind'] ?? '') === 'conversation' && filled($identity['mark_read_url'] ?? null))
                data-mark-read-url="{{ $identity['mark_read_url'] }}"
                data-conversation-key="{{ $selected['key'] ?? '' }}"
            @endif
        >
            @forelse ($thread['events'] ?? [] as $event)
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
                            · {{ $event['channel_label'] ?? '' }}
                            · {{ $event['occurred_at_label'] ?? '' }}
                        </p>
                        <p class="ops-comms-workspace__bubble-body">{{ $event['body'] ?? '' }}</p>
                        @if (filled($event['channel_label'] ?? null) || filled($event['occurred_at_label'] ?? null))
                            <details class="ops-comms-workspace__evidence">
                                <summary>Evidence</summary>
                                <ul class="ops-comms-workspace__evidence-list">
                                    @if (filled($event['channel_label'] ?? null))
                                        <li>{{ $event['channel_label'] }}</li>
                                    @endif
                                    @if (filled($event['occurred_at_label'] ?? null))
                                        <li>{{ $event['occurred_at_label'] }}</li>
                                    @endif
                                </ul>
                            </details>
                        @endif
                    </article>
                @endif
            @empty
                <p class="ops-comms-workspace__empty" data-conversation-empty>No messages yet.</p>
            @endforelse
        </div>

        @if (filled($thread['composer'] ?? null) && $section !== 'history' && in_array($selected['kind'] ?? '', ['conversation', 'call'], true))
            @include('operations.communications.workspace.partials.composer-panel', [
                'composer' => $thread['composer'],
                'section' => $section,
                'thread' => $thread,
            ])
        @endif
    @endif
</div>
