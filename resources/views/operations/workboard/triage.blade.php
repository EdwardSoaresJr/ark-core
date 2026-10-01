@php
    /** @var \App\Ark\Operations\Workboard\WorkboardQueueWorkspaceProjection $workboardWorkspace */
    $fragmentQuery = array_filter([
        'queue' => $workboardWorkspace->selectedQueueKey,
    ]);
    $fragmentUrl = route('operations.workboard.triage.fragment', $fragmentQuery);
@endphp

<x-operations.queue-page-header
    title="Workboard"
    description="What should I work on next?"
    :count="$workboardWorkspace->queueCount > 0 ? $workboardWorkspace->queueCount : null"
    :show-back="false"
/>

<section
    id="ops-workboard-triage-live"
    class="ops-ops-workspace"
    data-fragment-url="{{ $fragmentUrl }}"
    data-queue-count="{{ $workboardWorkspace->queueCount }}"
>
    @include('operations.workboard.partials.workspace-live-body', ['workboardWorkspace' => $workboardWorkspace])
</section>
