<?php

namespace App\Ark\Operations\Workboard;

use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Bounded Job Board list + Cards Kanban: exact lane totals, shared search/tech filters,
 * page-sized List projection, and per-column capped Kanban projection.
 *
 * Cards (Kanban) ignores the List lane tab so every workflow column stays visible.
 * Search and technician filters still apply to both presentations.
 * Focus (`?focus=lane_key`) is navigation state for Cards only — not a user preference.
 */
final readonly class JobBoardListPage
{
    public const PAGE_SIZE = 50;

    /** Visible cards loaded per Kanban column; column counts remain exact for the filtered set. */
    public const KANBAN_COLUMN_SIZE = 40;

    /** Initial / step size when a Kanban column is focused. */
    public const FOCUS_BATCH_SIZE = 40;

    /** Hard cap on cards projected for a single focused column request. */
    public const FOCUS_LIMIT_MAX = 200;

    public const SORT_UPDATED = 'updated';

    public const SORT_RO = 'ro';

    public const SORT_CUSTOMER = 'customer';

    public const SORT_VEHICLE = 'vehicle';

    public const SORT_STATUS = 'status';

    public const SORT_TECH = 'tech';

    public const SORT_TOTAL = 'total';

    public const SORT_ATTENTION = 'attention';

    /**
     * @param  Collection<int, RepairOrder>  $repairOrders
     * @param  list<array{key: string, label: string, color: string, count: int}>  $laneTabs
     * @param  list<JobBoardListRow>  $rows
     * @param  list<WorkboardTriageLaneProjection>  $pageColumns
     * @param  list<JobBoardKanbanColumn>  $kanbanColumns
     */
    public function __construct(
        public Collection $repairOrders,
        public array $laneTabs,
        public string $laneKey,
        public string $query,
        public string $techId,
        public string $sortKey,
        public string $sortDir,
        public int $queueTotal,
        public int $filteredTotal,
        public int $page,
        public int $perPage,
        public int $lastPage,
        public array $rows,
        public array $pageColumns,
        public Collection $pageRepairOrders,
        public array $kanbanColumns = [],
        public int $kanbanFilteredTotal = 0,
        public ?string $view = null,
        public ?string $focusKey = null,
        public int $focusLimit = self::FOCUS_BATCH_SIZE,
    ) {}

    /**
     * Search / tech filters that Cards (Kanban) honors. Lane tabs are List-only.
     */
    public function hasBoardWideFilters(): bool
    {
        return $this->query !== '' || $this->techId !== '';
    }

    public function isFocused(): bool
    {
        return $this->focusKey !== null && $this->focusKey !== '';
    }

    public function focusedColumn(): ?JobBoardKanbanColumn
    {
        if (! $this->isFocused()) {
            return null;
        }

        foreach ($this->kanbanColumns as $column) {
            if ($column->key === $this->focusKey) {
                return $column;
            }
        }

        return null;
    }

    public function from(): int
    {
        if ($this->filteredTotal === 0) {
            return 0;
        }

        return (($this->page - 1) * $this->perPage) + 1;
    }

    public function to(): int
    {
        if ($this->filteredTotal === 0) {
            return 0;
        }

        return min($this->filteredTotal, $this->page * $this->perPage);
    }

    public function hasFilters(): bool
    {
        return $this->laneKey !== 'all' || $this->query !== '' || $this->techId !== '';
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, string|int>
     */
    public function queryParams(array $overrides = []): array
    {
        $lane = array_key_exists('lane', $overrides) ? $overrides['lane'] : $this->laneKey;
        $query = array_key_exists('q', $overrides) ? $overrides['q'] : $this->query;
        $tech = array_key_exists('tech', $overrides) ? $overrides['tech'] : $this->techId;
        $sort = array_key_exists('sort', $overrides) ? $overrides['sort'] : $this->sortKey;
        $dir = array_key_exists('dir', $overrides) ? $overrides['dir'] : $this->sortDir;
        $page = array_key_exists('page', $overrides) ? (int) $overrides['page'] : $this->page;
        $focus = array_key_exists('focus', $overrides) ? $overrides['focus'] : $this->focusKey;
        $focusLimit = array_key_exists('focus_limit', $overrides)
            ? (int) $overrides['focus_limit']
            : $this->focusLimit;

        $params = [];

        if (is_string($lane) && $lane !== '' && $lane !== 'all') {
            $params['lane'] = $lane;
        }

        if (is_string($query) && $query !== '') {
            $params['q'] = $query;
        }

        if (is_string($tech) && $tech !== '') {
            $params['tech'] = $tech;
        }

        if (is_string($sort) && $sort !== '' && $sort !== self::SORT_UPDATED) {
            $params['sort'] = $sort;
        }

        if (is_string($dir) && $dir === 'asc') {
            $params['dir'] = 'asc';
        }

        // Layout preference is browser-local — never append ?view= to navigation.
        // Bookmarked ?view= is still accepted by JobBoardLayoutPreference / UiLayoutPreference.

        // Focus is Cards navigation state — never written to users.job_board_view.
        if (is_string($focus) && $focus !== '') {
            $params['focus'] = $focus;

            if ($focusLimit > self::FOCUS_BATCH_SIZE) {
                $params['focus_limit'] = min(self::FOCUS_LIMIT_MAX, max(self::FOCUS_BATCH_SIZE, $focusLimit));
            }
        }

        if ($page > 1) {
            $params['page'] = $page;
        }

        return $params;
    }

    public function focusUrl(string $laneKey): string
    {
        return route('operations.index', $this->queryParams([
            'focus' => $laneKey,
            'focus_limit' => self::FOCUS_BATCH_SIZE,
            'page' => 1,
        ]));
    }

    public function boardUrl(): string
    {
        return route('operations.index', $this->queryParams([
            'focus' => null,
            'focus_limit' => self::FOCUS_BATCH_SIZE,
            'page' => 1,
        ]));
    }

    public function focusLoadMoreUrl(): ?string
    {
        $column = $this->focusedColumn();

        if ($column === null || $column->hiddenCount <= 0) {
            return null;
        }

        $nextLimit = min(self::FOCUS_LIMIT_MAX, $this->focusLimit + self::FOCUS_BATCH_SIZE);

        if ($nextLimit <= $this->focusLimit) {
            return null;
        }

        return route('operations.index', $this->queryParams([
            'focus' => $this->focusKey,
            'focus_limit' => $nextLimit,
            'page' => 1,
        ]));
    }

    public function sortUrl(string $column): string
    {
        $dir = 'asc';

        if ($this->sortKey === $column) {
            $dir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } elseif (in_array($column, [self::SORT_UPDATED, self::SORT_TOTAL, self::SORT_ATTENTION], true)) {
            $dir = 'desc';
        }

        return route('operations.index', $this->queryParams([
            'sort' => $column,
            'dir' => $dir,
            'page' => 1,
        ]));
    }

    public function ariaSort(string $column): ?string
    {
        if ($this->sortKey !== $column) {
            return null;
        }

        return $this->sortDir === 'asc' ? 'ascending' : 'descending';
    }

    public function isSortActive(string $column): bool
    {
        return $this->sortKey === $column;
    }

    /**
     * @return list<string>
     */
    public static function sortableColumns(): array
    {
        return [
            self::SORT_RO,
            self::SORT_CUSTOMER,
            self::SORT_VEHICLE,
            self::SORT_STATUS,
            self::SORT_TECH,
            self::SORT_TOTAL,
            self::SORT_ATTENTION,
            self::SORT_UPDATED,
        ];
    }

    public static function parseSort(?string $sort): string
    {
        return in_array($sort, self::sortableColumns(), true)
            ? $sort
            : self::SORT_UPDATED;
    }

    public static function parseDir(?string $dir): string
    {
        return $dir === 'asc' ? 'asc' : 'desc';
    }

    public static function parseLane(?string $lane, array $laneTabs): string
    {
        $keys = collect($laneTabs)->pluck('key')->all();

        if (is_string($lane) && in_array($lane, $keys, true)) {
            return $lane;
        }

        return 'all';
    }

    public static function parsePage(Request $request): int
    {
        return max(1, (int) $request->integer('page', 1));
    }

    /**
     * @param  list<string>  $laneKeys
     */
    public static function parseFocus(?string $focus, array $laneKeys): ?string
    {
        if (! is_string($focus) || $focus === '') {
            return null;
        }

        return in_array($focus, $laneKeys, true) ? $focus : null;
    }

    public static function parseFocusLimit(Request $request): int
    {
        $raw = $request->query('focus_limit');

        if ($raw === null || $raw === '') {
            return self::FOCUS_BATCH_SIZE;
        }

        $limit = (int) $raw;

        if ($limit < self::FOCUS_BATCH_SIZE) {
            return self::FOCUS_BATCH_SIZE;
        }

        return min(self::FOCUS_LIMIT_MAX, $limit);
    }
}
