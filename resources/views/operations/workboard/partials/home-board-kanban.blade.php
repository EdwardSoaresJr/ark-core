@php
    /** @var list<\App\Ark\Operations\Workboard\JobBoardListRow> $jobBoardListRows */
    /** @var \App\Ark\Operations\Workboard\JobBoardListPage $jobBoardListPage */
    $kanbanColumns = $jobBoardListPage->kanbanColumns;
    $hasAnyCards = collect($kanbanColumns)->contains(fn ($column) => $column->totalCount > 0);
@endphp

<div data-job-board-cards data-job-board-kanban class="ark-job-board-cards ark-job-board-kanban">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-2 d-md-none" data-job-board-kanban-mobile-nav>
        <label for="job-board-kanban-column" class="form-label mb-0 small text-secondary">Column</label>
        <select id="job-board-kanban-column" class="form-select form-select-sm" data-job-board-kanban-column-select aria-label="Jump to Kanban column">
            @foreach ($kanbanColumns as $column)
                <option value="{{ $column->key }}">{{ $column->label }} ({{ $column->totalCount }})</option>
            @endforeach
        </select>
    </div>

    <p class="mb-2 text-secondary small" data-job-board-kanban-summary>
        @if (! $hasAnyCards)
            @if ($jobBoardListPage->hasBoardWideFilters())
                No repair orders match these filters.
            @else
                No open repair orders on the job board.
            @endif
        @else
            {{ $jobBoardListPage->kanbanFilteredTotal }}
            @if ($jobBoardListPage->hasBoardWideFilters())
                matching across columns
            @else
                on the board
            @endif
            · Queue total {{ $jobBoardListPage->queueTotal }}
            @if ($jobBoardListPage->laneKey !== 'all')
                <span class="text-secondary">· List queue “{{ collect($jobBoardListPage->laneTabs)->firstWhere('key', $jobBoardListPage->laneKey)['label'] ?? $jobBoardListPage->laneKey }}” applies only in List view</span>
            @endif
        @endif
        @if ($jobBoardListPage->hasBoardWideFilters())
            <a href="{{ route('operations.index', $jobBoardListPage->queryParams(['q' => '', 'tech' => '', 'page' => 1])) }}" class="btn btn-sm btn-ghost-secondary ms-1" data-job-board-kanban-clear>Clear filters</a>
        @endif
    </p>

    @if (! $hasAnyCards)
        <x-tabler.empty-state icon="{{ $jobBoardListPage->hasBoardWideFilters() ? 'search' : 'layout-grid' }}" :clear-url="$jobBoardListPage->hasBoardWideFilters() ? route('operations.index') : null">
            @if ($jobBoardListPage->hasBoardWideFilters())
                No repair orders match these filters.
            @else
                No open repair orders on the job board.
            @endif
        </x-tabler.empty-state>
    @else
        <div class="ark-job-board-kanban-scroller" data-job-board-kanban-scroller tabindex="0" aria-label="Job board Kanban columns">
            <div class="ark-job-board-kanban-track" data-job-board-kanban-track role="list">
                @foreach ($kanbanColumns as $column)
                    @php
                        $laneColor = \App\Ark\Operations\RepairOrders\Status\RepairOrderStatusColor::tablerColorForChipTone(
                            \App\Ark\Operations\RepairOrders\Status\RepairOrderStatusColor::chipTone($column->color),
                        );
                    @endphp
                    <section
                        id="ops-kanban-col-{{ $column->key }}"
                        class="ark-job-board-kanban-column card"
                        data-job-board-kanban-column="{{ $column->key }}"
                        data-lane-key="{{ $column->key }}"
                        data-lane-color="{{ $column->color }}"
                        role="listitem"
                        aria-labelledby="ops-kanban-col-{{ $column->key }}-title"
                    >
                        <div class="card-status-top bg-{{ $laneColor }}" aria-hidden="true"></div>
                        <header class="ark-job-board-kanban-column-head card-header">
                            <a
                                href="{{ $column->focusUrl ?? $jobBoardListPage->focusUrl($column->key) }}"
                                class="ark-job-board-kanban-column-focus text-reset text-decoration-none"
                                data-job-board-kanban-focus="{{ $column->key }}"
                                aria-label="Focus {{ $column->label }} — {{ $column->totalCount }} repair {{ $column->totalCount === 1 ? 'order' : 'orders' }}"
                            >
                                <h2 class="card-title mb-0" id="ops-kanban-col-{{ $column->key }}-title">
                                    {{ $column->label }}
                                    <span
                                        class="badge bg-secondary-lt text-secondary ms-1"
                                        data-job-board-kanban-count="{{ $column->key }}"
                                        title="Matching repair orders in this column"
                                    >{{ $column->totalCount }}</span>
                                </h2>
                            </a>
                        </header>
                        <div class="ark-job-board-kanban-column-body card-body" data-job-board-kanban-column-body>
                            @forelse ($column->rows as $row)
                                @include('operations.workboard.partials.home-board-card', ['row' => $row])
                            @empty
                                <p class="text-secondary small mb-0 py-3 text-center" data-job-board-kanban-empty>
                                    No repair orders
                                </p>
                            @endforelse
                        </div>
                        @if ($column->hiddenCount > 0 && $column->focusUrl)
                            <div class="card-footer">
                                <a
                                    href="{{ $column->focusUrl }}"
                                    class="btn btn-ghost-secondary w-100"
                                    data-job-board-kanban-more="{{ $column->key }}"
                                >+{{ $column->hiddenCount }} more — focus column</a>
                            </div>
                        @endif
                    </section>
                @endforeach
            </div>
        </div>
    @endif
</div>
