@php
    /** @var list<\App\Ark\Operations\Workboard\JobBoardListRow> $jobBoardListRows */
    /** @var \App\Ark\Operations\Workboard\JobBoardListPage $jobBoardListPage */
@endphp

<div data-job-board-cards class="ark-job-board-cards">
    @if ($jobBoardListRows === [])
        <x-tabler.empty-state icon="{{ $jobBoardListPage->hasFilters() ? 'search' : 'layout-grid' }}" :clear-url="$jobBoardListPage->hasFilters() ? route('operations.index') : null">
            @if ($jobBoardListPage->hasFilters())
                No repair orders match these filters.
            @else
                No open repair orders on the job board.
            @endif
        </x-tabler.empty-state>
    @else
        <div class="row row-cards g-2" data-job-board-cards-grid>
            @foreach ($jobBoardListRows as $row)
                <div class="col-12 col-sm-6 col-xl-4 col-xxl-3">
                    @include('operations.workboard.partials.home-board-card', ['row' => $row])
                </div>
            @endforeach
        </div>
    @endif
</div>
