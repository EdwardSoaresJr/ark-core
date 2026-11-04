@php
    $identity = is_array($thread['identity'] ?? null) ? $thread['identity'] : null;
    $phoneDigits = preg_replace('/\D+/', '', (string) ($identity['phone'] ?? ''));
    $showComposer = filled($thread['composer'] ?? null)
        && ($section ?? '') !== 'history'
        && in_array($selected['kind'] ?? '', ['conversation', 'call'], true);
    $listFilter = $listFilter ?? 'needs';
    $ownerFilter = $ownerFilter ?? 'everyone';
    $events = $thread['events'] ?? [];
    $deliveries = is_array($thread['deliveries'] ?? null) ? $thread['deliveries'] : [];
    $eventDeliveries = is_array($thread['event_deliveries'] ?? null) ? $thread['event_deliveries'] : [];
    $eventNotes = is_array($thread['event_notes'] ?? null) ? $thread['event_notes'] : [];
    $work = is_array($thread['decision']['work'] ?? null) ? $thread['decision']['work'] : null;
    $permission = is_array($identity['permission'] ?? null) ? $identity['permission'] : null;
    $latestDelivery = $deliveries === [] ? null : end($deliveries);
    $failedDelivery = is_array($latestDelivery) && $latestDelivery['failed'] ? $latestDelivery : null;
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
    <div class="card-header d-block py-2 ark-comms-thread-header" data-comms-thread-identity>
        @if ($backHref !== null && $identity === null)
            <a class="btn btn-sm btn-ghost-secondary d-lg-none mb-1 ps-0" href="{{ $backHref }}">
                <x-tabler.icon name="arrow-left" />
                Back to list
            </a>
        @endif
        @if ($identity !== null)
            {{-- Read and reply here; the customer, their details and the work controls are in the context lane. --}}
            <div class="d-flex align-items-center gap-2 ark-comms-thread-header-row">
                @if ($backHref !== null)
                    <a class="btn btn-icon btn-sm btn-ghost-secondary d-lg-none flex-shrink-0" href="{{ $backHref }}" aria-label="Back to list" title="Back to list">
                        <x-tabler.icon name="arrow-left" />
                    </a>
                @endif
                <div class="flex-fill overflow-hidden">
                    @php
                        // The status, and texting when it is not allowed, follow the name on wide screens and lead
                        // the second line on narrower ones, where the header's actions share the first line.
                        $badges = static fn (string $class): string => (filled($identity['lane_label'] ?? null)
                                ? '<span class="badge '.$laneBadge($identity['lane'] ?? 'needs').' flex-shrink-0 '.$class.'">'.e($identity['lane_label']).'</span>'
                                : '')
                            .(is_array($permission) && $permission['texting'] !== 'allowed'
                                ? '<span class="badge bg-'.e($permission['tone']).'-lt flex-shrink-0 '.$class.'" title="'.e($permission['detail'] ?? '').'" data-comms-texting="'.e($permission['texting']).'">'.e($permission['label']).'</span>'
                                : '');
                    @endphp
                    <div class="d-flex align-items-center column-gap-2 overflow-hidden">
                        <h3 class="card-title mb-0 text-truncate">{{ $identity['name'] ?? $thread['title'] ?? 'Unknown' }}</h3>
                        {!! $badges('d-none d-xl-inline-flex') !!}
                    </div>
                    <div class="d-flex flex-nowrap align-items-center column-gap-2 small text-secondary overflow-hidden" data-comms-thread-visit>
                        {!! $badges('d-xl-none') !!}
                        @if (filled($identity['ro_url'] ?? null))
                            <a href="{{ $identity['ro_url'] }}" class="fw-medium flex-shrink-0">{{ $identity['ro_label'] ?? 'Repair order' }}</a>
                        @elseif (filled($identity['ro_label'] ?? null))
                            <span class="flex-shrink-0">{{ $identity['ro_label'] }}</span>
                        @endif
                        @if (filled($identity['vehicle_label'] ?? null))
                            <span class="text-truncate">{{ $identity['vehicle_label'] }}</span>
                        @endif
                        @if (filled($identity['ro_status'] ?? null))
                            <span class="badge bg-secondary-lt flex-shrink-0 d-none d-sm-inline-flex">{{ $identity['ro_status'] }}</span>
                        @endif
                    </div>
                </div>
                {{-- Tablet and phone: the context lane is a separate view, so the common actions stay here. --}}
                <div class="btn-list ark-comms-actions flex-nowrap flex-shrink-0 d-xl-none">
                    @if ($phoneDigits !== '')
                        <a href="tel:{{ $phoneDigits }}" class="btn btn-sm" title="Call">
                            <x-tabler.icon name="phone" />
                            <span class="ark-comms-action-label">Call</span>
                        </a>
                    @endif
                    <button
                        type="button"
                        class="btn btn-sm"
                        data-comms-context-toggle
                        aria-label="Customer"
                        title="Customer"
                        :aria-pressed="contextOpen.toString()"
                        @click="contextOpen = ! contextOpen"
                    >
                        <x-tabler.icon name="user" />
                        <span class="ark-comms-action-label">Customer</span>
                    </button>
                    @if (is_array($work) && filled($work['url'] ?? null))
                        @include('operations.communications.workspace.partials.work-actions', ['work' => $work, 'variant' => 'menu'])
                    @endif
                </div>
            </div>
        @else
            <h3 class="card-title mb-0">Select a conversation</h3>
        @endif
    </div>

    @if (is_array($thread['decision'] ?? null))
        @include('operations.communications.workspace.partials.decision-panel', ['decision' => $thread['decision']])
    @endif

    <div class="ark-comms-thread-wrap position-relative d-flex flex-column flex-fill">
    <div
        id="comms-workspace-thread-messages"
        class="card-body chat-bubbles ark-comms-thread gap-2 overflow-auto flex-fill p-2 p-md-3"
        data-comms-thread-body
        aria-label="Conversation"
        @if (($section ?? '') !== 'history' && ($selected['kind'] ?? '') === 'conversation' && filled($identity['mark_read_url'] ?? null))
            data-mark-read-url="{{ $identity['mark_read_url'] }}"
            data-conversation-key="{{ $selected['key'] ?? '' }}"
        @endif
    >
        @if ($thread === null)
            <div class="text-secondary text-center my-auto">Select a conversation</div>
        @elseif ($events === [])
            <div class="text-secondary text-center my-auto">No messages yet.</div>
        @else
            @if (is_array($thread['older'] ?? null))
                <div class="text-center" data-comms-older data-before="{{ $thread['older']['before'] }}" data-key="{{ $thread['older']['key'] }}" data-loaded="{{ count($events) }}">
                    <button type="button" class="btn btn-sm btn-ghost-secondary" data-comms-older-load>Load earlier messages</button>
                </div>
            @endif
            @include('operations.communications.workspace.partials.thread-events', [
                'events' => $events,
                'eventDeliveries' => $eventDeliveries,
                'eventNotes' => $eventNotes,
                'eventKeys' => $thread['event_keys'] ?? [],
            ])
        @endif
    </div>
        <button type="button" class="btn btn-primary btn-sm rounded-pill shadow position-absolute bottom-0 start-50 translate-middle-x mb-2 d-none" data-comms-new-below>
            New messages
            <x-tabler.icon name="arrow-down" />
        </button>
    </div>

    @if ($showComposer && ($permission['texting'] ?? null) === 'opted_out')
        <div class="alert alert-danger m-2 mb-0 py-2 small" role="status" data-comms-texting-notice>
            <span class="fw-bold">{{ $permission['label'] }}.</span> {{ $permission['detail'] }}
        </div>
    @elseif ($showComposer && $failedDelivery !== null)
        <div class="alert alert-warning m-2 mb-0 py-2 small" role="status" data-comms-delivery-notice>
            <span class="fw-bold">Last text not delivered.</span> {{ $failedDelivery['detail'] }}
        </div>
    @endif

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
