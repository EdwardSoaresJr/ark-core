@php
    /** @var \App\Ark\Operations\Workboard\JobBoardListPage $jobBoardListPage */
    $column = $jobBoardListPage->focusedColumn();
    $loadMoreUrl = $jobBoardListPage->focusLoadMoreUrl();
    $shown = $column !== null ? count($column->rows) : 0;
    $total = $column?->totalCount ?? 0;
    $laneColor = $column !== null
        ? \App\Ark\Operations\RepairOrders\Status\RepairOrderStatusColor::tablerColorForChipTone(
            \App\Ark\Operations\RepairOrders\Status\RepairOrderStatusColor::chipTone($column->color),
        )
        : 'secondary';
@endphp

<div
    data-job-board-cards
    data-job-board-focus
    data-job-board-focus-lane="{{ $jobBoardListPage->focusKey }}"
    class="ark-job-board-cards ark-job-board-focus"
>
    <div class="page-header d-print-none mb-3" data-job-board-focus-header>
        <div class="row align-items-center g-2">
            <div class="col-auto">
                <a
                    href="{{ $jobBoardListPage->boardUrl() }}"
                    class="btn btn-outline-secondary"
                    data-job-board-focus-back
                >
                    <x-tabler.icon name="arrow-left" class="icon" />
                    Back to Board
                </a>
            </div>
            <div class="col">
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <span class="status-dot status-dot-animated bg-{{ $laneColor }}" aria-hidden="true"></span>
                    <h2 class="page-title mb-0" data-job-board-focus-title>
                        {{ $column?->label ?? 'Workflow stage' }}
                    </h2>
                    <span
                        class="badge bg-secondary-lt text-secondary"
                        data-job-board-focus-count
                        title="Matching repair orders in this stage"
                    >{{ $total }}</span>
                </div>
                <div class="text-secondary mt-1" data-job-board-focus-summary>
                    @if ($total === 0)
                        @if ($jobBoardListPage->hasBoardWideFilters())
                            No repair orders match these filters in this stage.
                        @else
                            No repair orders in this stage.
                        @endif
                    @elseif ($shown < $total)
                        Showing {{ $shown }} of {{ $total }} repair {{ $total === 1 ? 'order' : 'orders' }}
                    @else
                        {{ $total }} repair {{ $total === 1 ? 'order' : 'orders' }}
                    @endif
                    @if ($jobBoardListPage->hasBoardWideFilters())
                        <a
                            href="{{ route('operations.index', $jobBoardListPage->queryParams(['q' => '', 'tech' => '', 'page' => 1])) }}"
                            class="btn btn-sm btn-ghost-secondary ms-1"
                            data-job-board-focus-clear
                        >Clear filters</a>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if ($column === null || $total === 0)
        <x-tabler.empty-state icon="{{ $jobBoardListPage->hasBoardWideFilters() ? 'search' : 'layout-grid' }}" :clear-url="$jobBoardListPage->hasBoardWideFilters() ? route('operations.index', $jobBoardListPage->queryParams(['q' => '', 'tech' => '', 'page' => 1])) : null">
            @if ($jobBoardListPage->hasBoardWideFilters())
                No repair orders match these filters in this stage.
            @else
                No repair orders in this stage.
            @endif
        </x-tabler.empty-state>
    @else
        <div
            class="ark-job-board-focus-grid"
            data-job-board-focus-grid
            role="list"
            aria-label="{{ $column->label }} repair orders"
        >
            @foreach ($column->rows as $row)
                <div class="ark-job-board-focus-cell" role="listitem">
                    @include('operations.workboard.partials.home-board-card', ['row' => $row])
                </div>
            @endforeach
        </div>

        @if ($loadMoreUrl !== null)
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3" data-job-board-focus-more>
                <p class="text-secondary small mb-0">
                    Showing {{ $shown }} of {{ $total }}
                </p>
                <a
                    href="{{ $loadMoreUrl }}"
                    class="btn btn-outline-secondary"
                    data-job-board-focus-load-more
                >Load more</a>
            </div>
        @elseif ($shown < $total)
            <p class="text-secondary small mt-3 mb-0" data-job-board-focus-capped>
                Showing {{ $shown }} of {{ $total }}. Switch to List for the full stage.
                @if ($column->listUrl)
                    <a href="{{ $column->listUrl }}" class="ms-1">Open in List</a>
                @endif
            </p>
        @endif
    @endif
</div>
