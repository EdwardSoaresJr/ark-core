@php
    /** @var list<\App\Ark\Operations\Workboard\JobBoardListRow> $jobBoardListRows */
    /** @var \App\Ark\Operations\Workboard\JobBoardListPage $jobBoardListPage */
    $clearUrl = route('operations.index');
@endphp

<div data-job-board-list class="ark-job-board-list">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-2" data-job-board-list-summary>
        <p class="mb-0 text-secondary small" data-job-board-list-range>
            @if ($jobBoardListPage->filteredTotal === 0)
                No matching repair orders
            @else
                Showing {{ $jobBoardListPage->from() }}–{{ $jobBoardListPage->to() }}
                of {{ $jobBoardListPage->filteredTotal }}
                @if ($jobBoardListPage->hasFilters())
                    matching
                @endif
                · Queue total {{ $jobBoardListPage->queueTotal }}
            @endif
        </p>
        @if ($jobBoardListPage->hasFilters())
            <a href="{{ $clearUrl }}" class="btn btn-sm btn-ghost-secondary" data-job-board-list-clear>Clear filters</a>
        @endif
    </div>

    @if ($jobBoardListRows === [])
        <x-tabler.empty-state icon="{{ $jobBoardListPage->hasFilters() ? 'search' : 'list' }}" :clear-url="$jobBoardListPage->hasFilters() ? $clearUrl : null">
            @if ($jobBoardListPage->hasFilters())
                No repair orders match these filters.
            @else
                No open repair orders on the job board.
            @endif
        </x-tabler.empty-state>
    @else
        <div class="card d-none d-md-block" data-job-board-list-desktop>
            <div class="table-responsive">
                <table class="table table-vcenter table-hover card-table table-sm mb-0 ark-job-board-table">
                    <thead>
                        <tr>
                            <th scope="col" @if ($jobBoardListPage->ariaSort('ro')) aria-sort="{{ $jobBoardListPage->ariaSort('ro') }}" @endif>
                                <a href="{{ $jobBoardListPage->sortUrl('ro') }}" class="btn btn-ghost-secondary btn-sm px-1" data-job-board-sort="ro">RO #</a>
                            </th>
                            @php
                                $customerVehicleAriaSort = $jobBoardListPage->ariaSort('customer')
                                    ?? $jobBoardListPage->ariaSort('vehicle');
                            @endphp
                            <th
                                scope="col"
                                class="ark-job-board-col-customer-vehicle"
                                data-job-board-col="customer-vehicle"
                                @if ($customerVehicleAriaSort) aria-sort="{{ $customerVehicleAriaSort }}" @endif
                            >
                                <span class="d-inline-flex flex-wrap align-items-center column-gap-1">
                                    <a
                                        href="{{ $jobBoardListPage->sortUrl('customer') }}"
                                        class="btn btn-ghost-secondary btn-sm px-1"
                                        data-job-board-sort="customer"
                                        @if ($jobBoardListPage->ariaSort('customer')) aria-current="true" @endif
                                    >Customer</a>
                                    <span class="text-secondary" aria-hidden="true">/</span>
                                    <a
                                        href="{{ $jobBoardListPage->sortUrl('vehicle') }}"
                                        class="btn btn-ghost-secondary btn-sm px-1"
                                        data-job-board-sort="vehicle"
                                        @if ($jobBoardListPage->ariaSort('vehicle')) aria-current="true" @endif
                                    >Vehicle</a>
                                </span>
                            </th>
                            <th scope="col" class="ark-job-board-col-concern">Concern / Job</th>
                            <th scope="col" @if ($jobBoardListPage->ariaSort('status')) aria-sort="{{ $jobBoardListPage->ariaSort('status') }}" @endif>
                                <a href="{{ $jobBoardListPage->sortUrl('status') }}" class="btn btn-ghost-secondary btn-sm px-1" data-job-board-sort="status">Status</a>
                            </th>
                            <th scope="col">Tech</th>
                            <th scope="col" class="text-end">Total</th>
                            <th scope="col">
                                <span title="ARK attention signal, not a user-entered priority" data-job-board-sort="attention">Attention</span>
                            </th>
                            <th scope="col" class="ark-job-board-col-activity">Activity</th>
                            <th scope="col" @if ($jobBoardListPage->ariaSort('updated')) aria-sort="{{ $jobBoardListPage->ariaSort('updated') }}" @endif>
                                <a href="{{ $jobBoardListPage->sortUrl('updated') }}" class="btn btn-ghost-secondary btn-sm px-1" data-job-board-sort="updated">Updated</a>
                            </th>
                            <th scope="col" class="w-1"><span class="visually-hidden">Open</span></th>
                        </tr>
                    </thead>
                    <tbody data-job-board-list-body>
                        @foreach ($jobBoardListRows as $row)
                            @include('operations.workboard.partials.home-board-list-row', ['row' => $row])
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="d-md-none d-flex flex-column gap-2" data-job-board-list-mobile>
            @foreach ($jobBoardListRows as $row)
                @include('operations.workboard.partials.home-board-list-mobile-row', ['row' => $row])
            @endforeach
        </div>

        @if ($jobBoardListPage->lastPage > 1)
            <nav class="d-flex justify-content-between align-items-center mt-3" aria-label="Job board list pages" data-job-board-list-pagination>
                @if ($jobBoardListPage->page > 1)
                    <a class="btn btn-outline-secondary" href="{{ route('operations.index', $jobBoardListPage->queryParams(['page' => $jobBoardListPage->page - 1])) }}" data-job-board-list-prev>Previous</a>
                @else
                    <span class="btn btn-outline-secondary disabled" aria-disabled="true">Previous</span>
                @endif
                <span class="text-secondary small">Page {{ $jobBoardListPage->page }} of {{ $jobBoardListPage->lastPage }}</span>
                @if ($jobBoardListPage->page < $jobBoardListPage->lastPage)
                    <a class="btn btn-outline-secondary" href="{{ route('operations.index', $jobBoardListPage->queryParams(['page' => $jobBoardListPage->page + 1])) }}" data-job-board-list-next>Next</a>
                @else
                    <span class="btn btn-outline-secondary disabled" aria-disabled="true">Next</span>
                @endif
            </nav>
        @endif
    @endif
</div>
