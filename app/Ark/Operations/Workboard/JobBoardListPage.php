<?php

namespace App\Ark\Operations\Workboard;

use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Bounded Job Board list: exact lane totals, server filters, page-sized card projection only.
 */
final readonly class JobBoardListPage
{
    public const PAGE_SIZE = 50;

    public const SORT_UPDATED = 'updated';

    public const SORT_RO = 'ro';

    public const SORT_CUSTOMER = 'customer';

    public const SORT_VEHICLE = 'vehicle';

    public const SORT_STATUS = 'status';

    /**
     * @param  Collection<int, RepairOrder>  $repairOrders
     * @param  list<array{key: string, label: string, color: string, count: int}>  $laneTabs
     * @param  list<JobBoardListRow>  $rows
     * @param  list<WorkboardTriageLaneProjection>  $pageColumns
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
    ) {}

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

        $params = ['view' => JobBoardLayout::List->value];

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

        if ($page > 1) {
            $params['page'] = $page;
        }

        return $params;
    }

    public function sortUrl(string $column): string
    {
        $dir = 'asc';

        if ($this->sortKey === $column) {
            $dir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } elseif (in_array($column, [self::SORT_UPDATED], true)) {
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

    public static function parseSort(?string $sort): string
    {
        return in_array($sort, [self::SORT_UPDATED, self::SORT_RO, self::SORT_CUSTOMER, self::SORT_VEHICLE, self::SORT_STATUS], true)
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
}
