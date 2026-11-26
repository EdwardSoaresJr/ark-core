@php
    /** @var list<\App\Ark\Operations\Workboard\JobBoardListRow> $jobBoardListRows */
    /** @var \App\Ark\Operations\Workboard\JobBoardListPage $jobBoardListPage */
@endphp

<div data-job-board-list class="ark-job-board-list ark-job-board-hybrid">
    @if ($jobBoardListRows === [])
        <x-tabler.empty-state icon="{{ $jobBoardListPage->hasFilters() ? 'search' : 'list' }}" :clear-url="$jobBoardListPage->hasFilters() ? route('operations.index') : null">
            @if ($jobBoardListPage->hasFilters())
                No repair orders match these filters.
            @else
                No open repair orders on the job board.
            @endif
        </x-tabler.empty-state>
    @else
        @php
            $customerVehicleAriaSort = $jobBoardListPage->ariaSort('customer')
                ?? $jobBoardListPage->ariaSort('vehicle');
        @endphp
        <div
            class="ark-job-board-hybrid__sort"
            data-job-board-list-sort
            data-job-board-col="customer-vehicle"
            @if ($customerVehicleAriaSort) aria-sort="{{ $customerVehicleAriaSort }}" @endif
        >
            <span class="ark-job-board-hybrid__sort-label">Sort</span>
            <div class="ark-job-board-hybrid__sort-links" role="group" aria-label="Sort job board list">
                @include('operations.workboard.partials.home-board-list-sort-link', ['column' => 'ro', 'label' => 'RO #'])
                @include('operations.workboard.partials.home-board-list-sort-link', ['column' => 'vehicle', 'label' => 'Vehicle'])
                @include('operations.workboard.partials.home-board-list-sort-link', ['column' => 'customer', 'label' => 'Customer'])
                @include('operations.workboard.partials.home-board-list-sort-link', ['column' => 'status', 'label' => 'Status'])
                @include('operations.workboard.partials.home-board-list-sort-link', ['column' => 'tech', 'label' => 'Tech'])
                @include('operations.workboard.partials.home-board-list-sort-link', ['column' => 'total', 'label' => 'Amount'])
                @include('operations.workboard.partials.home-board-list-sort-link', ['column' => 'attention', 'label' => 'Attention'])
                @include('operations.workboard.partials.home-board-list-sort-link', ['column' => 'updated', 'label' => 'Updated'])
            </div>
        </div>

        <div class="ark-job-board-hybrid__records" data-job-board-list-body>
            @foreach ($jobBoardListRows as $row)
                @include('operations.workboard.partials.home-board-list-row', ['row' => $row])
            @endforeach
        </div>
    @endif
</div>
