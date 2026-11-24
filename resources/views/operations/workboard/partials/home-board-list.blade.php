@php
    /** @var list<\App\Ark\Operations\Workboard\JobBoardListRow> $jobBoardListRows */
    /** @var \App\Ark\Operations\Workboard\JobBoardListPage $jobBoardListPage */
@endphp

<div data-job-board-list class="ark-job-board-list">
    @if ($jobBoardListRows === [])
        <x-tabler.empty-state icon="{{ $jobBoardListPage->hasFilters() ? 'search' : 'list' }}" :clear-url="$jobBoardListPage->hasFilters() ? route('operations.index') : null">
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
                                @include('operations.workboard.partials.home-board-list-sort-link', ['column' => 'ro', 'label' => 'RO #'])
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
                                    @include('operations.workboard.partials.home-board-list-sort-link', ['column' => 'customer', 'label' => 'Customer'])
                                    <span class="text-secondary" aria-hidden="true">/</span>
                                    @include('operations.workboard.partials.home-board-list-sort-link', ['column' => 'vehicle', 'label' => 'Vehicle'])
                                </span>
                            </th>
                            <th scope="col" class="ark-job-board-col-concern">
                                <span class="ark-job-board-th-static">Concern / Job</span>
                            </th>
                            <th scope="col" @if ($jobBoardListPage->ariaSort('status')) aria-sort="{{ $jobBoardListPage->ariaSort('status') }}" @endif>
                                @include('operations.workboard.partials.home-board-list-sort-link', ['column' => 'status', 'label' => 'Status'])
                            </th>
                            <th scope="col" @if ($jobBoardListPage->ariaSort('tech')) aria-sort="{{ $jobBoardListPage->ariaSort('tech') }}" @endif>
                                @include('operations.workboard.partials.home-board-list-sort-link', ['column' => 'tech', 'label' => 'Tech'])
                            </th>
                            <th scope="col" class="text-end" @if ($jobBoardListPage->ariaSort('total')) aria-sort="{{ $jobBoardListPage->ariaSort('total') }}" @endif>
                                @include('operations.workboard.partials.home-board-list-sort-link', ['column' => 'total', 'label' => 'Total'])
                            </th>
                            <th scope="col" @if ($jobBoardListPage->ariaSort('attention')) aria-sort="{{ $jobBoardListPage->ariaSort('attention') }}" @endif>
                                @include('operations.workboard.partials.home-board-list-sort-link', ['column' => 'attention', 'label' => 'Attention'])
                            </th>
                            <th scope="col" class="ark-job-board-col-activity">
                                <span class="ark-job-board-th-static">Activity</span>
                            </th>
                            <th scope="col" @if ($jobBoardListPage->ariaSort('updated')) aria-sort="{{ $jobBoardListPage->ariaSort('updated') }}" @endif>
                                @include('operations.workboard.partials.home-board-list-sort-link', ['column' => 'updated', 'label' => 'Updated'])
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
    @endif
</div>
