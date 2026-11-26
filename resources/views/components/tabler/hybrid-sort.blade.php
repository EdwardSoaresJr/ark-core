@props([
    'sort',
    'routeName',
    'filterQuery' => [],
    'columns' => [],
    'label' => 'Sort list',
])

@php
    /** @var \App\Ark\Ui\Collection\CollectionSortState $sort */
    /** @var array<string, string> $columns */
    $selectId = 'hybrid-sort-'.\Illuminate\Support\Str::slug($routeName);
    $activeDirLabel = $sort->dir === 'asc' ? 'ascending' : 'descending';
    $nextDirLabel = $sort->dir === 'asc' ? 'descending' : 'ascending';
    $dirToggleUrl = $sort->url($sort->key, $routeName, $filterQuery);
@endphp

<div
    class="ark-collection-hybrid__sort ark-collection-hybrid__sort--links"
    data-collection-hybrid-sort
>
    <span class="ark-collection-hybrid__sort-label">Sort</span>
    <div class="ark-collection-hybrid__sort-links" role="group" aria-label="{{ $label }}">
        @foreach ($columns as $key => $columnLabel)
            @php
                $active = $sort->isActive($key);
                $ariaSort = $sort->ariaSort($key);
            @endphp
            <a
                href="{{ $sort->url($key, $routeName, $filterQuery) }}"
                @class(['ark-job-board-sort', 'is-active' => $active])
                data-collection-sort="{{ $key }}"
                data-sort-dir="{{ $active ? $sort->dir : '' }}"
                @if ($active) aria-current="true" @endif
                @if ($ariaSort) aria-sort="{{ $ariaSort }}" @endif
            >
                <span class="ark-job-board-sort__label">{{ $columnLabel }}</span>
                <span class="ark-job-board-sort__indicators" aria-hidden="true">
                    <span @class(['ark-job-board-sort__chevron', 'is-on' => $active && $sort->dir === 'asc'])>▲</span>
                    <span @class(['ark-job-board-sort__chevron', 'is-on' => $active && $sort->dir === 'desc'])>▼</span>
                </span>
            </a>
        @endforeach
    </div>
</div>

<form
    method="get"
    action="{{ route($routeName) }}"
    class="ark-collection-hybrid__sort ark-collection-hybrid__sort--compact"
    data-collection-hybrid-sort-compact
    aria-label="{{ $label }}"
>
    @foreach ($filterQuery as $name => $value)
        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
    @endforeach
    <label class="visually-hidden" for="{{ $selectId }}">Sort by</label>
    <select
        id="{{ $selectId }}"
        name="sort"
        class="form-select form-select-sm ark-collection-hybrid__sort-select"
        onchange="this.form.submit()"
    >
        @foreach ($columns as $key => $columnLabel)
            <option value="{{ $key }}" @selected($sort->key === $key)>{{ $columnLabel }}</option>
        @endforeach
    </select>
    <a
        href="{{ $dirToggleUrl }}"
        class="btn btn-sm btn-outline-secondary ark-collection-hybrid__sort-dir"
        aria-label="Sorted {{ $activeDirLabel }}; switch to {{ $nextDirLabel }}"
    >
        <span aria-hidden="true">{{ $sort->dir === 'asc' ? '↑' : '↓' }}</span>
        <span class="ark-collection-hybrid__sort-dir-label">{{ $sort->dir === 'asc' ? 'Asc' : 'Desc' }}</span>
    </a>
    <noscript><button type="submit" class="btn btn-sm btn-outline-secondary">Sort</button></noscript>
</form>
