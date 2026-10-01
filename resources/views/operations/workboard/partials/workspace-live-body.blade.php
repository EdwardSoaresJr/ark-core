@php
    /** @var \App\Ark\Operations\Workboard\WorkboardQueueWorkspaceProjection $workboardWorkspace */
@endphp

<div class="ops-ops-workspace__grid">
    @include('operations.workboard.partials.queue-nav', ['workboardWorkspace' => $workboardWorkspace])

    <div id="ops-workboard-queue-panel" class="ops-ops-workspace__queue" aria-label="Work queue">
        @include('operations.workboard.partials.queue-work-panel', ['workboardWorkspace' => $workboardWorkspace])
    </div>
</div>
