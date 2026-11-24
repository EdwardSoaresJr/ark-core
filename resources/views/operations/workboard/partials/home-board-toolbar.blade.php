@php
    /** @var \App\Ark\Operations\Workboard\JobBoardListPage $jobBoardListPage */
    /** @var string $jobBoardLayoutMode */
    $listActive = $jobBoardLayoutMode === \App\Ark\Operations\Workboard\JobBoardLayout::List->value;
    $cardsActive = $jobBoardLayoutMode === \App\Ark\Operations\Workboard\JobBoardLayout::Cards->value;
@endphp

<form
    method="get"
    action="{{ route('operations.index') }}"
    class="row g-2 align-items-end ark-job-board-toolbar"
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

    <div class="col-12 col-lg">
        <label class="form-label visually-hidden" for="job-board-search">Search job board</label>
        <div class="input-icon">
            <span class="input-icon-addon" aria-hidden="true">
                <x-tabler.icon name="search" />
            </span>
            <input
                id="job-board-search"
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

    <div class="col-12 col-sm-6 col-md-4 col-lg-auto ark-job-board-toolbar-tech">
        <label class="form-label" for="job-board-tech">Employee</label>
        <select
            id="job-board-tech"
            name="tech"
            class="form-select"
            aria-label="Employee"
            onchange="this.form.submit()"
        >
            <option value="">All employees</option>
            @foreach ($homeBoardTechnicians as $technician)
                <option value="{{ $technician->id }}" @selected((string) $jobBoardListPage->techId === (string) $technician->id)>{{ $technician->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-6 col-sm-auto">
        <button type="submit" class="btn btn-outline-secondary w-100 w-sm-auto">Search</button>
    </div>

    <div class="col-6 col-sm-auto ms-sm-auto ms-lg-0">
        <div
            class="nav nav-segmented justify-content-stretch ark-job-board-layout-toggle"
            role="group"
            aria-label="Job board layout"
            data-job-board-layout-toggle
        >
            <button
                type="button"
                class="nav-link flex-fill{{ $listActive ? ' active' : '' }}"
                data-job-board-layout-option="list"
                aria-pressed="{{ $listActive ? 'true' : 'false' }}"
                @if ($listActive) aria-current="true" @endif
            >List</button>
            <button
                type="button"
                class="nav-link flex-fill{{ $cardsActive ? ' active' : '' }}"
                data-job-board-layout-option="cards"
                aria-pressed="{{ $cardsActive ? 'true' : 'false' }}"
                @if ($cardsActive) aria-current="true" @endif
            >Cards</button>
        </div>
    </div>

    @can('repair_orders.manage')
        <div class="col-12 col-sm-auto">
            <a href="{{ route('operations.intake.create') }}" class="btn btn-primary w-100 w-sm-auto" data-ark-workspace="off">
                <x-tabler.icon name="plus" />
                Check In
            </a>
        </div>
    @endcan
</form>
