@php
    /** @var \App\Ark\Operations\Workboard\JobBoardLayout $jobBoardLayout */
    $boardUrl = route('operations.index', ['view' => 'board']);
    $listUrl = route('operations.index', ['view' => 'list']);
@endphp

<div class="row g-2 align-items-center" aria-label="Job board filters" role="group" data-job-board-filters>
    <div class="col-12 col-md">
        <div class="input-icon">
            <span class="input-icon-addon">
                <x-tabler.icon name="search" />
            </span>
            <input
                type="search"
                class="form-control"
                placeholder="Search job board"
                aria-label="Search job board"
                x-model="query"
                autocomplete="off"
                spellcheck="false"
            >
        </div>
    </div>
    <div class="col col-md-auto">
        <select class="form-select" x-model="techId" aria-label="Employee">
            <option value="">All employees</option>
            @foreach ($homeBoardTechnicians as $technician)
                <option value="{{ $technician->id }}">{{ $technician->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-auto">
        <div class="btn-group" role="group" aria-label="Job board layout">
            <a
                href="{{ $boardUrl }}"
                @class(['btn', $jobBoardLayout->value === 'board' ? 'btn-primary' : 'btn-outline-secondary'])
                data-job-board-layout-toggle="board"
                @if ($jobBoardLayout->value === 'board') aria-current="page" @endif
            >
                <x-tabler.icon name="layout-grid" />
                <span class="d-none d-sm-inline">Board</span>
            </a>
            <a
                href="{{ $listUrl }}"
                @class(['btn', $jobBoardLayout->value === 'list' ? 'btn-primary' : 'btn-outline-secondary'])
                data-job-board-layout-toggle="list"
                @if ($jobBoardLayout->value === 'list') aria-current="page" @endif
            >
                <x-tabler.icon name="list" />
                <span class="d-none d-sm-inline">List</span>
            </a>
        </div>
    </div>
    @can('repair_orders.manage')
        <div class="col-auto">
            <a href="{{ route('operations.intake.create') }}" class="btn btn-primary" data-ark-workspace="off">
                <x-tabler.icon name="plus" />
                Check In
            </a>
        </div>
    @endcan
</div>
