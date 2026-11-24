@php
    /** @var \App\Ark\Operations\Workboard\JobBoardListPage $jobBoardListPage */
    /** @var string $column */
    /** @var string $label */
    $ariaSort = $jobBoardListPage->ariaSort($column);
    $active = $jobBoardListPage->isSortActive($column);
    $nextDir = $active
        ? ($jobBoardListPage->sortDir === 'asc' ? 'desc' : 'asc')
        : (in_array($column, ['updated', 'total', 'attention'], true) ? 'desc' : 'asc');
@endphp
<a
    href="{{ $jobBoardListPage->sortUrl($column) }}"
    class="ark-job-board-sort{{ $active ? ' is-active' : '' }}"
    data-job-board-sort="{{ $column }}"
    data-sort-dir="{{ $active ? $jobBoardListPage->sortDir : '' }}"
    @if ($active) aria-current="true" @endif
    aria-label="Sort by {{ $label }}, {{ $active ? ($jobBoardListPage->sortDir === 'asc' ? 'ascending' : 'descending') : 'activate' }}; activates {{ $nextDir }}ending"
>
    <span class="ark-job-board-sort__label">{{ $label }}</span>
    <span class="ark-job-board-sort__indicators" aria-hidden="true">
        <span @class(['ark-job-board-sort__chevron', 'is-on' => $active && $jobBoardListPage->sortDir === 'asc'])>▲</span>
        <span @class(['ark-job-board-sort__chevron', 'is-on' => $active && $jobBoardListPage->sortDir === 'desc'])>▼</span>
    </span>
</a>
