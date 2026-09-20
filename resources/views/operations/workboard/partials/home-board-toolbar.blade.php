<div class="ops-job-board-toolbar" aria-label="Job board filters">
    <label class="ops-job-board-toolbar__search">
        <span class="sr-only">Search job board</span>
        <svg class="ops-job-board-toolbar__search-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11ZM2 9a7 7 0 1 1 12.452 4.391l3.328 3.329a.75.75 0 1 1-1.06 1.06l-3.329-3.328A7 7 0 0 1 2 9Z" clip-rule="evenodd" />
        </svg>
        <input
            type="search"
            class="ops-job-board-toolbar__input"
            placeholder="Search job board"
            x-model="query"
            autocomplete="off"
            spellcheck="false"
        >
    </label>

    <label class="ops-job-board-toolbar__filter">
        <span class="ops-job-board-toolbar__filter-label">Employee</span>
        <select class="ops-job-board-toolbar__select" x-model="techId">
            <option value="">All employees</option>
            @foreach ($homeBoardTechnicians as $technician)
                <option value="{{ $technician->id }}">{{ $technician->name }}</option>
            @endforeach
        </select>
    </label>

    @can('repair_orders.manage')
        <a
            href="{{ route('operations.intake.create') }}"
            class="ops-job-board-toolbar__check-in"
            data-ark-workspace="off"
        >+ Check In</a>
    @endcan
</div>
