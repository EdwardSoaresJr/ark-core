@props([
    'id',
    'action',
    'query' => '',
    'placeholder',
    'label',
    'clearUrl',
    'hasFilters' => false,
    'activeFilterCount' => 0,
])

{{-- The slot holds the secondary filter columns: col-12 col-md-4 col-xxl-auto, or col-6 for a pair of dates. --}}
<div class="card mb-3">
    <div class="card-body p-2 p-md-3">
        <form method="GET" action="{{ $action }}" role="search" aria-label="{{ $label }}">
            {{ $hidden ?? '' }}

            <div class="row g-2 align-items-end">
                <div class="col col-lg">
                    <label for="{{ $id }}-search" class="form-label d-none d-md-block">Search</label>
                    <div class="input-icon">
                        <span class="input-icon-addon">
                            <x-tabler.icon name="search" />
                        </span>
                        <input
                            id="{{ $id }}-search"
                            name="q"
                            value="{{ $query }}"
                            type="search"
                            autofocus
                            autocomplete="off"
                            placeholder="{{ $placeholder }}"
                            aria-label="{{ $label }}"
                            class="form-control"
                        >
                    </div>
                </div>

                <div class="col-auto order-lg-last">
                    <div class="btn-list flex-nowrap">
                        <button type="submit" class="btn btn-primary">Search</button>
                        @if ($hasFilters)
                            <a href="{{ $clearUrl }}" class="btn btn-ghost-secondary d-none d-md-inline-flex">Clear</a>
                        @endif
                    </div>
                </div>

                <div class="col-12 d-md-none">
                    <div class="d-flex align-items-center">
                        <button
                            type="button"
                            class="btn btn-sm btn-ghost-secondary"
                            data-bs-toggle="collapse"
                            data-bs-target="#{{ $id }}-filters"
                            aria-controls="{{ $id }}-filters"
                            aria-expanded="{{ $activeFilterCount > 0 ? 'true' : 'false' }}"
                        >
                            <x-tabler.icon name="adjustments-horizontal" />
                            Filters
                            @if ($activeFilterCount > 0)
                                <span class="badge bg-primary text-primary-fg ms-2">{{ $activeFilterCount }}</span>
                            @endif
                        </button>
                        @if ($hasFilters)
                            <a href="{{ $clearUrl }}" class="btn btn-sm btn-ghost-secondary ms-auto">Clear</a>
                        @endif
                    </div>
                </div>

                <div @class(['col-12 col-lg-6 col-xxl-auto collapse d-md-block', 'show' => $activeFilterCount > 0]) id="{{ $id }}-filters">
                    <div class="row g-2">
                        {{ $slot }}
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
