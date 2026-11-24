@php
    /** @var list<\App\Ark\Operations\Workboard\JobBoardListRow> $jobBoardListRows */
@endphp

<div data-job-board-list>
    @if ($jobBoardListRows === [])
        <x-tabler.empty-state icon="list">
            No open repair orders on the job board.
        </x-tabler.empty-state>
    @else
        <div
            x-show="visibleRowCount() === 0"
            x-cloak
            data-job-board-list-empty
        >
            <x-tabler.empty-state icon="search">
                No repair orders match these filters.
            </x-tabler.empty-state>
        </div>

        {{-- Dense desktop/tablet table --}}
        <div class="card d-none d-md-block" data-job-board-list-desktop x-show="visibleRowCount() > 0" x-cloak>
            <div class="table-responsive">
                <table class="table table-vcenter table-hover card-table table-sm mb-0 ark-job-board-table">
                    <thead>
                        <tr>
                            <th scope="col">
                                <button type="button" class="btn btn-ghost-secondary btn-sm px-1" @click="setSort('ro')" data-job-board-sort="ro">RO #</button>
                            </th>
                            <th scope="col">
                                <button type="button" class="btn btn-ghost-secondary btn-sm px-1" @click="setSort('customer')" data-job-board-sort="customer">Customer</button>
                            </th>
                            <th scope="col">
                                <button type="button" class="btn btn-ghost-secondary btn-sm px-1" @click="setSort('vehicle')" data-job-board-sort="vehicle">Vehicle</button>
                            </th>
                            <th scope="col">Concern / Job</th>
                            <th scope="col">
                                <button type="button" class="btn btn-ghost-secondary btn-sm px-1" @click="setSort('status')" data-job-board-sort="status">Status</button>
                            </th>
                            <th scope="col">
                                <button type="button" class="btn btn-ghost-secondary btn-sm px-1" @click="setSort('technician')" data-job-board-sort="technician">Technician</button>
                            </th>
                            <th scope="col">
                                <button type="button" class="btn btn-ghost-secondary btn-sm px-1" @click="setSort('promised')" data-job-board-sort="promised">Promised</button>
                            </th>
                            <th scope="col">Parts</th>
                            <th scope="col">
                                <button type="button" class="btn btn-ghost-secondary btn-sm px-1" @click="setSort('attention')" data-job-board-sort="attention">Priority</button>
                            </th>
                            <th scope="col">
                                <button type="button" class="btn btn-ghost-secondary btn-sm px-1" @click="setSort('updated')" data-job-board-sort="updated">Updated</button>
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

        {{-- Narrow mobile list --}}
        <div class="d-md-none d-flex flex-column gap-2" data-job-board-list-mobile x-show="visibleRowCount() > 0">
            @foreach ($jobBoardListRows as $row)
                @include('operations.workboard.partials.home-board-list-mobile-row', ['row' => $row])
            @endforeach
        </div>
    @endif
</div>
