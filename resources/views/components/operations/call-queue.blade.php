@props(['pollerOnly' => false])

@can(App\Ark\Runtime\Authorization\ArkCapability::OperationsAccess->value)
    @php
        $markWorkedUrl = str_replace('0', '__CALL_SESSION__', route('operations.telephony.call-queue.worked', ['callSession' => 0]));
        $claimUrl = str_replace('0', '__CALL_SESSION__', route('operations.telephony.calls.claim', ['callSession' => 0]));
        $markReadUrl = str_replace('0', '__CONVERSATION__', route('operations.conversations.read', ['conversation' => 0]));

        // Poller chrome only — share Attention with ops layout (never includeRecentActivity).
        // Full-panel mode still needs recent activity for SSR bootstrap.
        $previousLastSeen = request()->attributes->get('operations.previous_last_seen_at');
        $previousLastSeenAt = $previousLastSeen instanceof \Illuminate\Support\Carbon
            ? $previousLastSeen
            : (is_string($previousLastSeen) ? \Illuminate\Support\Carbon::parse($previousLastSeen) : null);
        $resolver = app(\App\Ark\Operations\Communications\CommunicationsQueueResolver::class);
        $callQueuePayload = $pollerOnly
            ? $resolver->resolveAttention(request()->user(), $previousLastSeenAt)
            : $resolver->resolve(request()->user(), $previousLastSeenAt);
        $callQueueCount = (int) ($callQueuePayload['count'] ?? 0);
        $callQueueSummary = $callQueuePayload['summary'] ?? [];
        $callQueueTriggerClass = match ($callQueueSummary['urgency'] ?? 'idle') {
            'live' => 'ops-call-queue__trigger--live',
            'attention' => 'ops-call-queue__trigger--attention',
            default => 'ops-call-queue__trigger--idle',
        };
        $callQueueCountClass = match (true) {
            ($callQueueSummary['has_live_calls'] ?? false) => 'ops-call-queue__count--live',
            ($callQueueSummary['since_last_shift_count'] ?? 0) > 0 => 'ops-call-queue__count--shift',
            $callQueueCount > 0 => 'ops-call-queue__count--attention',
            default => '',
        };
        $callQueueTriggerTitle = $callQueueCount === 0
            ? 'Attention — nothing needs attention'
            : 'Attention — '.implode(' · ', array_filter([
                $callQueueSummary['trigger_label'] ?? '',
                $callQueueSummary['breakdown_label'] ?? '',
            ]));
        $callQueueBootstrap = [
            'count' => $callQueueCount,
            'summary' => $callQueueSummary,
            'items' => $callQueuePayload['items'] ?? [],
            'calls' => $callQueuePayload['calls'] ?? [],
            'messages' => $callQueuePayload['messages'] ?? [],
            'queue_url' => $callQueuePayload['queue_url'] ?? '',
        ];
    @endphp
    <script type="application/json" id="ark-call-queue-bootstrap">@json($callQueueBootstrap)</script>
    <div
        data-call-queue-root
        x-data="arkCallQueue()"
        x-init="init()"
        @click.outside="open = false"
        @class([
            'ops-call-queue',
            'ops-call-queue--poller-only' => $pollerOnly,
        ])
        @if ($pollerOnly) aria-hidden="true" @endif
    >
        @unless ($pollerOnly)
        <button
            type="button"
            x-ref="trigger"
            class="ops-call-queue__trigger {{ $callQueueTriggerClass }}"
            :class="{
                'ops-call-queue__trigger--idle': urgency === 'idle',
                'ops-call-queue__trigger--attention': urgency === 'attention',
                'ops-call-queue__trigger--live': urgency === 'live',
            }"
            @click.stop="toggle()"
            :aria-expanded="open"
            :title="triggerTitle()"
            title="{{ $callQueueTriggerTitle }}"
            aria-haspopup="true"
        >
            <span class="ops-call-queue__trigger-copy">
                <span class="ops-call-queue__label">Attention</span>
                <span
                    class="ops-call-queue__trigger-meta"
                    x-text="summary.trigger_label"
                    @unless ($callQueueCount > 0) x-show="count > 0" x-cloak @endunless
                >{{ $callQueueCount > 0 ? ($callQueueSummary['trigger_label'] ?? '') : '' }}</span>
                <span
                    class="ops-call-queue__trigger-detail"
                    x-show="count > 0 && summary.breakdown_label"
                    x-text="summary.breakdown_label"
                    @unless ($callQueueCount > 0 || ($callQueueSummary['breakdown_label'] ?? '') === '') x-cloak @endunless
                >{{ $callQueueCount > 0 ? ($callQueueSummary['breakdown_label'] ?? '') : '' }}</span>
            </span>
            <span
                class="ops-call-queue__count {{ $callQueueCountClass }}"
                :class="countToneClass()"
                x-text="count"
                @unless ($callQueueCount > 0) x-show="count > 0" x-cloak @endunless
            >{{ $callQueueCount > 0 ? $callQueueCount : '' }}</span>
        </button>
        @endunless

        @unless ($pollerOnly)
        <div
            x-show="open"
            x-cloak
            :style="panelStyle"
            class="ops-call-queue__panel"
            role="dialog"
            aria-label="Attention queue"
            @click.stop
        >
            <div
                class="ops-call-queue__panel-head"
                :class="{
                    'ops-call-queue__panel-head--idle': urgency === 'idle',
                    'ops-call-queue__panel-head--attention': urgency === 'attention',
                    'ops-call-queue__panel-head--live': urgency === 'live',
                }"
            >
                <div>
                    <div class="ops-call-queue__panel-title-row">
                        <p class="ops-call-queue__panel-title">Attention</p>
                        <span
                            class="ops-call-queue__panel-count"
                            :class="countToneClass()"
                            x-show="count > 0"
                            x-text="count"
                            x-cloak
                        ></span>
                    </div>
                    <p
                        class="ops-call-queue__panel-note"
                        x-show="count > 0 && summary.trigger_label"
                        x-text="summary.trigger_label"
                        x-cloak
                    ></p>
                    <p
                        class="ops-call-queue__panel-note"
                        x-show="count > 0 && summary.breakdown_label"
                        x-text="summary.breakdown_label"
                        x-cloak
                    ></p>
                    <p
                        class="ops-call-queue__panel-note ops-call-queue__panel-note--shift"
                        x-show="count > 0 && summary.since_last_shift_count > 0"
                        x-text="`${summary.since_last_shift_count} since your last shift`"
                        x-cloak
                    ></p>
                </div>
                <p class="ops-call-queue__panel-note ops-call-queue__panel-note--status" x-show="loading" x-cloak>Refreshing…</p>
            </div>

            <div x-ref="itemsRoot" class="ops-call-queue__items">
                @include('operations.communications.partials.call-queue-items-list', [
                    'items' => $callQueuePayload['items'] ?? [],
                ])
            </div>

            <div class="ops-call-queue__panel-foot">
                <a
                    :href="queueUrl || '{{ \App\Ark\Operations\Communications\CommunicationsNeedsYou::url() }}'"
                    class="ops-call-queue__panel-link"
                >View full queue</a>
            </div>
        </div>
        @endunless
    </div>
@endcan
