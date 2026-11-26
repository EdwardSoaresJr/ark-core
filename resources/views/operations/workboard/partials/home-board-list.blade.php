@php
    /** @var list<\App\Ark\Operations\Workboard\JobBoardListRow> $jobBoardListRows */
    /** @var \App\Ark\Operations\Workboard\JobBoardListPage $jobBoardListPage */

    $sortOptions = [
        'ro' => 'RO #',
        'vehicle' => 'Vehicle',
        'customer' => 'Customer',
        'status' => 'Status',
        'tech' => 'Tech',
        'total' => 'Amount',
        'attention' => 'Attention',
        'updated' => 'Updated',
    ];
    $nextDir = $jobBoardListPage->sortDir === 'asc' ? 'desc' : 'asc';
    $dirToggleUrl = route('operations.index', $jobBoardListPage->queryParams([
        'sort' => $jobBoardListPage->sortKey,
        'dir' => $nextDir,
        'page' => 1,
    ]));
    $customerVehicleAriaSort = $jobBoardListPage->ariaSort('customer')
        ?? $jobBoardListPage->ariaSort('vehicle');
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
        <div
            class="ark-job-board-hybrid__sort ark-job-board-hybrid__sort--links"
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

        <form
            method="get"
            action="{{ route('operations.index') }}"
            class="ark-job-board-hybrid__sort ark-job-board-hybrid__sort--compact"
            data-job-board-list-sort-compact
            aria-label="Sort job board list"
        >
            @foreach ($jobBoardListPage->queryParams(['sort' => null, 'dir' => null, 'page' => 1]) as $name => $value)
                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
            @endforeach
            @if ($jobBoardListPage->sortDir === 'asc')
                <input type="hidden" name="dir" value="asc">
            @endif
            <label class="visually-hidden" for="job-board-sort-field">Sort by</label>
            <select
                id="job-board-sort-field"
                name="sort"
                class="form-select form-select-sm ark-job-board-hybrid__sort-select"
                onchange="this.form.submit()"
            >
                @foreach ($sortOptions as $value => $label)
                    <option value="{{ $value }}" @selected($jobBoardListPage->sortKey === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <a
                href="{{ $dirToggleUrl }}"
                class="btn btn-sm btn-outline-secondary ark-job-board-hybrid__sort-dir"
                aria-label="Sort {{ $jobBoardListPage->sortDir === 'asc' ? 'ascending' : 'descending' }}; switch to {{ $nextDir }}ending"
            >
                <span aria-hidden="true">{{ $jobBoardListPage->sortDir === 'asc' ? '↑' : '↓' }}</span>
                <span class="ark-job-board-hybrid__sort-dir-label">{{ $jobBoardListPage->sortDir === 'asc' ? 'Asc' : 'Desc' }}</span>
            </a>
        </form>

        <div class="ark-job-board-hybrid__records" data-job-board-list-body>
            @foreach ($jobBoardListRows as $row)
                @include('operations.workboard.partials.home-board-list-row', ['row' => $row])
            @endforeach
        </div>
    @endif
</div>
