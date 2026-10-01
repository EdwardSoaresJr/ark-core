@php
    $followUpCount = (int) ($follow_ups['total_count'] ?? 0);
    $followUpOverdueCount = (int) ($follow_ups['overdue_count'] ?? 0);
    $followUpOverdueOpportunity = $follow_ups['overdue_opportunity_label'] ?? null;
    $scheduledDecisionCount = (int) ($scheduled_decisions['total_count'] ?? 0);
    $taskCount = (int) ($tasks['total_count'] ?? 0);
    $scheduleCount = (int) ($schedule['total_count'] ?? 0);
    $taskBandCount = $taskCount + (($appointments_enabled ?? false) ? $scheduleCount : 0);
@endphp

<div class="ops-work-tab-panel">
    <section class="ops-work-zone ops-work-zone--planned">
        <div class="ops-work-planned-grid">
            <section class="ops-home-band ops-home-band--tasks ops-home-band--top-row" aria-labelledby="ops-work-tasks">
                @include('operations.work.partials.work-queue-band-header', [
                    'id' => 'ops-work-tasks',
                    'title' => 'Tasks',
                    'count' => $taskBandCount,
                    'queue' => 'tasks',
                    'compact' => true,
                    'addLabel' => 'Add task',
                    'addStoreRoute' => route('operations.work.tasks.store'),
                ])

                <div class="ops-home-band-body p-0">
                    @include('operations.work.partials.queue-tasks-body')
                </div>
            </section>

            <section class="ops-home-band ops-home-band--follow-ups ops-home-band--top-row" aria-labelledby="ops-work-follow-ups">
                @include('operations.work.partials.work-queue-band-header', [
                    'id' => 'ops-work-follow-ups',
                    'title' => 'Follow-Ups',
                    'count' => $followUpCount,
                    'queue' => 'follow-ups',
                    'compact' => true,
                    'subtitle' => $followUpOverdueCount > 0
                        ? $followUpOverdueCount.' overdue'.(filled($followUpOverdueOpportunity) ? ' · '.$followUpOverdueOpportunity : '')
                        : null,
                    'addLabel' => 'Add follow-up',
                    'addStoreRoute' => route('operations.work.follow-ups.store'),
                ])

                <div class="ops-home-band-body p-0">
                    @include('operations.work.partials.queue-follow-ups-body')
                </div>
            </section>

            <section class="ops-home-band ops-home-band--scheduled ops-home-band--top-row" aria-labelledby="ops-work-scheduled">
                @include('operations.work.partials.work-queue-band-header', [
                    'id' => 'ops-work-scheduled',
                    'title' => 'Scheduled',
                    'count' => $scheduledDecisionCount,
                    'queue' => 'scheduled',
                    'compact' => true,
                ])

                <div class="ops-home-band-body p-0">
                    @include('operations.work.partials.queue-scheduled-body')
                </div>
            </section>
        </div>
    </section>

    <section class="ops-work-zone ops-work-zone--decisions">
        <h2 class="sr-only">Customer Decisions</h2>

        @include('operations.work.partials.queue-decisions-body', ['compactActions' => true])
    </section>
</div>
