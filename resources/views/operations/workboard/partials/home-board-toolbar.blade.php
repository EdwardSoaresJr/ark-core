@php
    /** @var \App\Ark\Operations\Workboard\JobBoardListPage $jobBoardListPage */
@endphp

<form
    method="get"
    action="{{ route('operations.index') }}"
    class="row g-2 align-items-center"
    aria-label="Job board filters"
    role="search"
    data-job-board-filters
    data-job-board-list-search
>
    @if ($jobBoardListPage->laneKey !== 'all')
        <input type="hidden" name="lane" value="{{ $jobBoardListPage->laneKey }}">
    @endif
    @if ($jobBoardListPage->sortKey !== 'updated')
        <input type="hidden" name="sort" value="{{ $jobBoardListPage->sortKey }}">
    @endif
    @if ($jobBoardListPage->sortDir !== 'desc')
        <input type="hidden" name="dir" value="{{ $jobBoardListPage->sortDir }}">
    @endif

    <div class="col-12 col-md">
        <div class="input-icon">
            <span class="input-icon-addon">
                <x-tabler.icon name="search" />
            </span>
            <input
                type="search"
                name="q"
                value="{{ $jobBoardListPage->query }}"
                class="form-control"
                placeholder="Search RO #, customer, vehicle, concern"
                aria-label="Search job board"
                autocomplete="off"
                spellcheck="false"
            >
        </div>
    </div>
    <div class="col col-md-auto">
        <select name="tech" class="form-select" aria-label="Employee" onchange="this.form.submit()">
            <option value="">All employees</option>
            @foreach ($homeBoardTechnicians as $technician)
                <option value="{{ $technician->id }}" @selected((string) $jobBoardListPage->techId === (string) $technician->id)>{{ $technician->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-outline-secondary">Search</button>
    </div>
    @can('repair_orders.manage')
        <div class="col-auto">
            <a href="{{ route('operations.intake.create') }}" class="btn btn-primary" data-ark-workspace="off">
                <x-tabler.icon name="plus" />
                Check In
            </a>
        </div>
    @endcan
</form>
