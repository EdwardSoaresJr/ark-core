@php
    /** @var \App\Ark\Operations\Workboard\JobBoardListPage $jobBoardListPage */
@endphp

@if ($jobBoardListPage->isFocused() && $jobBoardListPage->focusedColumn() !== null)
    @include('operations.workboard.partials.home-board-focus')
@else
    @include('operations.workboard.partials.home-board-kanban')
@endif
