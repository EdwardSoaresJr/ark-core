@php
    /** @var \App\Ark\Operations\Work\WorkQueue $work_queue */

    $taskCount = (int) ($tasks['total_count'] ?? 0);
    $scheduleCount = (int) ($schedule['total_count'] ?? 0);
    $taskBandCount = $taskCount + (($appointments_enabled ?? false) ? $scheduleCount : 0);
    $followUpCount = (int) ($follow_ups['total_count'] ?? 0);
    $followUpOverdueCount = (int) ($follow_ups['overdue_count'] ?? 0);
    $followUpOverdueOpportunity = $follow_ups['overdue_opportunity_label'] ?? null;
    $scheduledDecisionCount = (int) ($scheduled_decisions['total_count'] ?? 0);
    $decisionPressureCount = (int) ($customer_decision_pressure['total_count'] ?? 0);

    $queueCount = match ($work_queue) {
        \App\Ark\Operations\Work\WorkQueue::Tasks => $taskBandCount,
        \App\Ark\Operations\Work\WorkQueue::FollowUps => $followUpCount,
        \App\Ark\Operations\Work\WorkQueue::Scheduled => $scheduledDecisionCount,
        \App\Ark\Operations\Work\WorkQueue::Comms => (int) ($customer_pressure_count ?? 0),
        \App\Ark\Operations\Work\WorkQueue::Decisions => $decisionPressureCount,
    };

    $followUpSubtitle = null;
    if ($work_queue === \App\Ark\Operations\Work\WorkQueue::FollowUps && $followUpOverdueCount > 0) {
        $followUpSubtitle = $followUpOverdueCount.' overdue';
        if (filled($followUpOverdueOpportunity)) {
            $followUpSubtitle .= ' · '.$followUpOverdueOpportunity;
        }
    }

    $queuePageTone = str_replace('ops-home-band--', '', $work_queue->bandClass());
    $addLabel = $work_queue === \App\Ark\Operations\Work\WorkQueue::Tasks ? 'Add task' : ($work_queue === \App\Ark\Operations\Work\WorkQueue::FollowUps ? 'Add follow-up' : null);
    $addStoreRoute = match ($work_queue) {
        \App\Ark\Operations\Work\WorkQueue::Tasks => route('operations.work.tasks.store'),
        \App\Ark\Operations\Work\WorkQueue::FollowUps => route('operations.work.follow-ups.store'),
        default => null,
    };
    $queueDescription = $work_queue === \App\Ark\Operations\Work\WorkQueue::FollowUps && filled($followUpSubtitle)
        ? $followUpSubtitle
        : $work_queue->description();
@endphp

<x-operations.app :title="$work_queue->label()">
    <section class="ops-work-queue-page">
        @if (session('comms_gate') || session('status'))
            <div class="ops-board-shell mb-3">
                @if (session('comms_gate'))
                    <div class="border border-rose-300 bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-950" role="alert">
                        {{ (int) (session('comms_gate.count') ?? 0) }} customer {{ ((int) (session('comms_gate.count') ?? 0)) === 1 ? 'contact' : 'contacts' }} still need action. Clear the communications queue before opening other ARK pages.
                    </div>
                @endif

                @if (session('status'))
                    <div class="border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-950" role="status">
                        {{ session('status') }}
                    </div>
                @endif
            </div>
        @endif

        <x-operations.queue-page-header
            :id="'ops-work-queue-'.$work_queue->value"
            :title="$work_queue->label()"
            :description="$queueDescription"
            :count="$queueCount"
            :tone="$queuePageTone"
        >
            @if (filled($addLabel) && filled($addStoreRoute))
                <x-slot:actions>
                    @include('operations.work.partials.advisor-work-item-add', [
                        'label' => $addLabel,
                        'storeRoute' => $addStoreRoute,
                    ])
                </x-slot:actions>
            @endif
        </x-operations.queue-page-header>

        <section class="ops-home-band {{ $work_queue->bandClass() }} ops-home-band--queue-page">
            <div class="ops-home-band-body p-0">
                @switch($work_queue)
                    @case(\App\Ark\Operations\Work\WorkQueue::Tasks)
                        @include('operations.work.partials.queue-tasks-body')
                        @break

                    @case(\App\Ark\Operations\Work\WorkQueue::FollowUps)
                        @if ($followUpOverdueCount > 0)
                            <p class="border-b border-slate-200 px-3 py-2 text-xs font-bold text-rose-800">
                                {{ $followUpOverdueCount }} overdue follow-up{{ $followUpOverdueCount === 1 ? '' : 's' }}
                                @if (filled($followUpOverdueOpportunity))
                                    <span class="text-rose-950">· {{ $followUpOverdueOpportunity }} opportunity</span>
                                @endif
                            </p>
                        @endif
                        @include('operations.work.partials.queue-follow-ups-body')
                        @break

                    @case(\App\Ark\Operations\Work\WorkQueue::Scheduled)
                        @include('operations.work.partials.queue-scheduled-body')
                        @break

                    @case(\App\Ark\Operations\Work\WorkQueue::Comms)
                        <div class="space-y-3">
                            @include('operations.work.partials.queue-comms-body', ['full' => true])
                        </div>
                        @break

                    @case(\App\Ark\Operations\Work\WorkQueue::Decisions)
                        @include('operations.work.partials.queue-decisions-body', ['full' => true])
                        @break
                @endswitch
            </div>
        </section>
    </section>
</x-operations.app>
