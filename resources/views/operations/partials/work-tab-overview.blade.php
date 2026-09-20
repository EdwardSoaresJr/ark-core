@php
    /** @var \App\Ark\Operations\Today\AdvisorTodayProjection $morningBrief */
    $commitments = $morningBrief->commitments;
    $hasCommitmentPressure = $commitments->dueTodayCount > 0 || $commitments->overdueCount > 0;
@endphp

<div class="ops-work-tab-panel ops-today">
    <div class="ops-today__overview ops-today__overview--two-col">
        @include('operations.today.partials.flow', ['flow' => $morningBrief->flow])
        @include('operations.today.partials.pipeline', ['pipeline' => $morningBrief->pipeline])
    </div>

    @if ($hasCommitmentPressure)
        @include('operations.today.partials.commitments', ['commitments' => $commitments])
    @endif

    <p class="ops-morning-brief__workboard">
        <a href="{{ route('operations.workboard') }}" class="ops-page-link ops-page-link--primary">Open workboard</a>
        <span class="ops-morning-brief__workboard-copy">Shop floor lanes — customer waiting, approval, parts, pickup.</span>
    </p>
</div>
