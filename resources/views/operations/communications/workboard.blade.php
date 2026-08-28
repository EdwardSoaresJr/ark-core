@php
    /** @var list<array<string, mixed>> $calls_waiting */
    /** @var list<array<string, mixed>> $new_opportunities */
    /** @var list<array<string, mixed>> $needs_shop */
    /** @var list<array<string, mixed>> $waiting_customer */
    /** @var list<array<string, mixed>> $recently_resolved */
    /** @var list<array<string, mixed>> $since_last_shift */
    /** @var list<array<string, mixed>> $recent_activity */
    /** @var array<string, int> $counts */

    $actionableCount = (int) ($counts['total_actionable'] ?? 0);
    $sinceLastShiftCount = count($since_last_shift ?? []);
@endphp

<x-operations.app title="Communications">
    <section class="space-y-2">
        <x-operations.queue-page-header
            id="ops-comms-workboard"
            title="Communications"
            description="Calls, leads, and threads by whose turn it is — one triage surface."
            :count="$actionableCount"
            :show-back="false"
        >
            <x-slot:actions>
                <a href="{{ \App\Ark\Operations\Communications\CommunicationsNeedsYou::url() }}" class="ops-page-link">Needs attention</a>
                <a href="{{ route('operations.index') }}" class="ops-page-link">Work</a>
            </x-slot:actions>
        </x-operations.queue-page-header>

        @if (session('status'))
            <div class="ops-board-shell border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-950" role="status">
                {{ session('status') }}
            </div>
        @endif

        @if (session('comms_gate'))
            <div class="ops-board-shell border border-rose-300 bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-950" role="alert">
                {{ (int) (session('comms_gate.count') ?? 0) }} customer {{ ((int) (session('comms_gate.count') ?? 0)) === 1 ? 'contact' : 'contacts' }} still need action. Clear communications before opening other ARK pages.
            </div>
        @endif

        @include('operations.communications.partials.channel-tabs')

        <div
            id="ops-comms-workboard-live"
            data-fragment-url="{{ route('operations.communications.workboard.fragment') }}"
        >
            <div class="ops-workboard-grid">
                <div class="ops-radar">
                    @include('operations.communications.partials.workboard-lane', [
                        'id' => 'ops-comms-lane-calls',
                        'label' => 'Calls',
                        'description' => 'Live and waiting — ring, missed, and voicemail.',
                        'count' => $counts['calls_waiting'] ?? 0,
                        'tone' => 'ready',
                        'rows' => $calls_waiting,
                        'cardPartial' => 'operations.communications.partials.workboard-card-call',
                        'emptyLabel' => 'No calls waiting.',
                    ])

                    @include('operations.communications.partials.workboard-lane', [
                        'id' => 'ops-comms-lane-new',
                        'label' => 'New',
                        'description' => 'Inbound leads — website, SMS, and acquisition.',
                        'count' => $counts['new_opportunities'] ?? 0,
                        'tone' => 'motion',
                        'rows' => $new_opportunities,
                        'cardPartial' => 'operations.communications.partials.workboard-card-lead',
                        'emptyLabel' => 'No open leads.',
                    ])

                    @include('operations.communications.partials.workboard-lane', [
                        'id' => 'ops-comms-lane-needs-shop',
                        'label' => 'Needs shop',
                        'description' => 'Threads waiting on the shop — reply or resolve.',
                        'count' => $counts['needs_shop'] ?? 0,
                        'tone' => 'approval',
                        'rows' => $needs_shop,
                        'cardPartial' => 'operations.communications.partials.workboard-card-conversation',
                        'emptyLabel' => 'Nothing needs the shop.',
                    ])

                    @include('operations.communications.partials.workboard-lane', [
                        'id' => 'ops-comms-lane-waiting-customer',
                        'label' => 'Waiting customer',
                        'description' => 'Ball is with the customer — follow up when needed.',
                        'count' => $counts['waiting_customer'] ?? 0,
                        'tone' => 'motion',
                        'rows' => $waiting_customer,
                        'cardPartial' => 'operations.communications.partials.workboard-card-waiting-customer',
                        'emptyLabel' => 'No threads waiting on customers.',
                    ])

                    @include('operations.communications.partials.workboard-lane', [
                        'id' => 'ops-comms-lane-recently-resolved',
                        'label' => 'Recently resolved',
                        'description' => 'Closed threads in the last 7 days.',
                        'count' => $counts['recently_resolved'] ?? 0,
                        'tone' => 'ready',
                        'rows' => $recently_resolved,
                        'cardPartial' => 'operations.communications.partials.workboard-card-conversation',
                        'emptyLabel' => 'No recently resolved threads.',
                    ])
                </div>
            </div>
        </div>

        @include('operations.communications.partials.queue-section', [
            'title' => 'Since Last Shift',
            'count' => $sinceLastShiftCount,
            'note' => ($since_last_shift_boundary_label ?? '') !== ''
                ? 'Since you were last in ARK ('.$since_last_shift_boundary_label.'). Oldest first.'
                : 'Since you were last in ARK. Oldest first.',
            'rows' => $since_last_shift ?? [],
            'empty' => 'Nothing new since your last shift.',
            'show_timestamp' => true,
        ])

        <div
            id="ops-work-recent-activity"
            class="ops-board-shell border-0"
            data-fragment-url="{{ route('operations.communications.recent-activity.fragment', request()->only('channel')) }}"
        >
            <div class="border-b border-slate-200 px-3 py-2">
                <h3 class="text-sm font-black text-slate-950">
                    Recent Activity
                    <span
                        data-recent-activity-count
                        class="font-normal text-slate-500"
                        @if (($recent_activity ?? []) === []) hidden @endif
                    >
                        @if (($recent_activity ?? []) !== [])
                            ({{ count($recent_activity) }})
                        @endif
                    </span>
                </h3>
                <p class="mt-0.5 text-[11px] leading-4 text-slate-500">Calls and messages in the last {{ \App\Ark\Operations\Communications\CommunicationsQueueWindow::HOURS }} hours.</p>
            </div>
            <div class="border-t border-slate-100" data-recent-activity-list>
                @include('operations.communications.partials.recent-activity-list', [
                    'rows' => $recent_activity ?? [],
                ])
            </div>
        </div>
    </section>
</x-operations.app>
