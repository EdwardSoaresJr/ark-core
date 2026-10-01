@php
    $isTechnicianBoard = ($workboardLens ?? \App\Ark\Operations\Workboard\WorkboardLens::ADVISOR) === \App\Ark\Operations\Workboard\WorkboardLens::TECHNICIAN;
    $isAdvisorTriage = ! $isTechnicianBoard && isset($workboardWorkspace);
@endphp

<x-operations.app :title="$isTechnicianBoard ? 'Tech Operations' : 'Operations'">
    @if ($isAdvisorTriage)
        @include('operations.workboard.triage')
    @else
        @include('operations.workboard.technician-lanes')
    @endif
</x-operations.app>
