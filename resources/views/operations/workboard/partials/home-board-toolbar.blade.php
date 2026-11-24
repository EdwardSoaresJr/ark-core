@php
    /** @var \App\Ark\Operations\Workboard\JobBoardLayout $jobBoardLayout */
    /** @var \App\Ark\Operations\Workboard\JobBoardListPage|null $jobBoardListPage */
    $isList = $jobBoardLayout->isList();
    $boardUrl = route('operations.index', ['view' => 'board']);
    $listUrl = route('operations.index', array_filter([
        'view' => 'list',
        'lane' => ($jobBoardListPage?->laneKey ?? 'all') !== 'all' ? $jobBoardListPage?->laneKey : null,
        'q' => filled($jobBoardListPage?->query) ? $jobBoardListPage?->query : null,
        'tech' => filled($jobBoardListPage?->techId) ? $jobBoardListPage?->techId : null,
    ]));
@endphp

<div class="row g-2 align-items-center" aria-label="Job board filters" role="group" data-job-board-filters>
    @if ($isList)
        <form method="get" action="{{ route('operations.index') }}" class="col-12 col-md" data-job-board-list-search>
            <input type="hidden" name="view" value="list">
            @if (($jobBoardListPage?->laneKey ?? 'all') !== 'all')
                <input type="hidden" name="lane" value="{{ $jobBoardListPage->laneKey }}">
            @endif
            @if (filled($jobBoardListPage?->sortKey) && $jobBoardListPage->sortKey !== 'updated')
                <input type="hidden" name="sort" value="{{ $jobBoardListPage->sortKey }}">
            @endif
            @if (($jobBoardListPage?->sortDir ?? 'desc') !== 'desc')
                <input type="hidden" name="dir" value="{{ $jobBoardListPage->sortDir }}">
            @endif
            <div class="row g-2 align-items-center">
                <div class="col">
                    <div class="input-icon">
                        <span class="input-icon-addon">
                            <x-tabler.icon name="search" />
                        </span>
                        <input
                            type="search"
                            name="q"
                            value="{{ $jobBoardListPage?->query }}"
                            class="form-control"
                            placeholder="Search job board"
                            aria-label="Search job board"
                            autocomplete="off"
                            spellcheck="false"
                        >
                    </div>
                </div>
                <div class="col-auto">
                    <select name="tech" class="form-select" aria-label="Employee" onchange="this.form.submit()">
                        <option value="">All employees</option>
                        @foreach ($homeBoardTechnicians as $technician)
                            <option value="{{ $technician->id }}" @selected((string) ($jobBoardListPage?->techId) === (string) $technician->id)>{{ $technician->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-outline-secondary">Search</button>
                </div>
            </div>
        </form>
    @else
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
    @endif
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
