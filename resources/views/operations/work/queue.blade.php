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

@php
    $queueToneColor = match ($queuePageTone) {
        'tasks' => 'green',
        'follow-ups' => 'purple',
        'scheduled' => 'yellow',
        'decision' => 'orange',
        default => 'azure',
    };
@endphp

<x-operations.app :title="$work_queue->label()" ui="tabler">
    <x-tabler.page
        layout="fluid"
        :title="$work_queue->label()"
        :title-id="'ops-work-queue-'.$work_queue->value"
        :title-count="$queueCount"
        :subtitle="$queueDescription"
        actions-label="Queue actions"
    >
        <x-slot:actions>
            @if (filled($addLabel) && filled($addStoreRoute))
                @include('operations.work.partials.advisor-work-item-add', [
                    'label' => $addLabel,
                    'storeRoute' => $addStoreRoute,
                ])
            @endif
        </x-slot:actions>

        <div class="d-flex flex-column gap-3" data-work-queue="{{ $work_queue->value }}">
            @php
                $queueSwitcher = [
                    [\App\Ark\Operations\Work\WorkQueue::Tasks, $taskBandCount],
                    [\App\Ark\Operations\Work\WorkQueue::FollowUps, $followUpCount],
                    [\App\Ark\Operations\Work\WorkQueue::Scheduled, $scheduledDecisionCount],
                    [\App\Ark\Operations\Work\WorkQueue::Decisions, $decisionPressureCount],
                ];
            @endphp
            <nav class="nav nav-segmented flex-nowrap overflow-auto align-self-start mw-100" aria-label="Work queues" data-work-queue-nav>
                @foreach ($queueSwitcher as [$switchQueue, $switchCount])
                    <a
                        href="{{ route('operations.work.queue', $switchQueue) }}"
                        @class(['nav-link flex-shrink-0 text-nowrap', 'active' => $switchQueue === $work_queue])
                        @if ($switchQueue === $work_queue) aria-current="page" @endif
                    >
                        {{ $switchQueue->label() }}
                        @if ($switchCount > 0)
                            <span class="badge bg-secondary-lt ms-1">{{ $switchCount }}</span>
                        @endif
                    </a>
                @endforeach
            </nav>

            @if (session('comms_gate'))
                <div class="alert alert-danger mb-0" role="alert">
                    {{ (int) (session('comms_gate.count') ?? 0) }} customer {{ ((int) (session('comms_gate.count') ?? 0)) === 1 ? 'contact' : 'contacts' }} still need action. Clear the communications queue before opening other ARK pages.
                </div>
            @endif
            @if (session('status'))
                <div class="alert alert-success mb-0" role="status">{{ session('status') }}</div>
            @endif

            @switch($work_queue)
                @case(\App\Ark\Operations\Work\WorkQueue::Tasks)
                    <div class="card">
                        <div class="card-status-top bg-{{ $queueToneColor }}"></div>
                        @include('operations.work.partials.queue-tasks-body')
                    </div>
                    @break

                @case(\App\Ark\Operations\Work\WorkQueue::FollowUps)
                    <div class="card">
                        <div class="card-status-top bg-{{ $queueToneColor }}"></div>
                        @if ($followUpOverdueCount > 0)
                            <div class="card-body py-2 small fw-bold text-danger border-bottom">
                                {{ $followUpOverdueCount }} overdue follow-up{{ $followUpOverdueCount === 1 ? '' : 's' }}
                                @if (filled($followUpOverdueOpportunity))
                                    · {{ $followUpOverdueOpportunity }} opportunity
                                @endif
                            </div>
                        @endif
                        @include('operations.work.partials.queue-follow-ups-body')
                    </div>
                    @break

                @case(\App\Ark\Operations\Work\WorkQueue::Scheduled)
                    <div class="card">
                        <div class="card-status-top bg-{{ $queueToneColor }}"></div>
                        @include('operations.work.partials.queue-scheduled-body')
                    </div>
                    @break

                @case(\App\Ark\Operations\Work\WorkQueue::Comms)
                    @include('operations.work.partials.queue-comms-body', ['full' => true])
                    @break

                @case(\App\Ark\Operations\Work\WorkQueue::Decisions)
                    @include('operations.work.partials.queue-decisions-body', ['full' => true])
                    @break
            @endswitch
        </div>
    </x-tabler.page>
</x-operations.app>
