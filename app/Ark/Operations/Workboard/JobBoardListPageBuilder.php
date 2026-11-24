<?php

namespace App\Ark\Operations\Workboard;

use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\RepairOrders\PartsPressure;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Operations\Today\AdvisorHomeCardSurface;
use App\Ark\Operations\Today\AdvisorHomeCardSurfaceProjection;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class JobBoardListPageBuilder
{
    public function __construct(
        private readonly WorkboardTriageProjection $workboardTriage,
        private readonly AdvisorHomeCardSurfaceProjection $homeCardSurfaces,
        private readonly EstimateTotalsCalculator $totalsCalculator,
        private readonly JobBoardListRowProjection $listRows,
    ) {}

    /**
     * @param  Collection<int, RepairOrder>  $repairOrders
     */
    public function build(Request $request, Collection $repairOrders): JobBoardListPage
    {
        $laneMeta = WorkboardSwimlaneCatalog::advisorHomeBoardColumns();
        $byLane = $this->bucketByLane($repairOrders, $laneMeta);
        $laneTabs = $this->listRows->laneTabsFromCounts($laneMeta, $byLane);
        $laneKey = JobBoardListPage::parseLane($request->query('lane'), $laneTabs);
        $query = trim((string) $request->query('q', ''));
        $techId = trim((string) $request->query('tech', ''));
        $sortKey = JobBoardListPage::parseSort($request->query('sort'));
        $sortDir = JobBoardListPage::parseDir($request->query('dir'));
        $page = JobBoardListPage::parsePage($request);
        $perPage = JobBoardListPage::PAGE_SIZE;

        $candidates = $laneKey === 'all'
            ? $byLane->flatten(1)
            : ($byLane->get($laneKey) ?? collect());

        $filtered = $candidates
            ->filter(fn (RepairOrder $repairOrder): bool => $this->matchesSearch($repairOrder, $query))
            ->filter(fn (RepairOrder $repairOrder): bool => $this->matchesTech($repairOrder, $techId))
            ->values();

        $sorted = $this->sortRepairOrders($filtered, $sortKey, $sortDir);
        $filteredTotal = $sorted->count();
        $lastPage = max(1, (int) ceil($filteredTotal / $perPage));
        $page = min($page, $lastPage);
        $pageRepairOrders = $sorted
            ->slice(($page - 1) * $perPage, $perPage)
            ->values();

        $pageColumns = $this->pageColumns($pageRepairOrders, $byLane, $laneMeta);
        $totals = $pageRepairOrders->mapWithKeys(fn (RepairOrder $repairOrder): array => [
            $repairOrder->id => $this->totalsCalculator->totalsFor($repairOrder),
        ]);
        $surfaces = $this->homeCardSurfaces->mapForHomeBoard(
            new \Illuminate\Database\Eloquent\Collection($pageRepairOrders->all()),
            $pageColumns,
            $totals,
        );
        $rowsByRepairOrderId = collect($this->listRows->fromHomeBoard($pageColumns, $surfaces))
            ->keyBy(fn (JobBoardListRow $row): int => $row->card->repairOrder->id);
        $rows = $pageRepairOrders
            ->map(fn (RepairOrder $repairOrder): ?JobBoardListRow => $rowsByRepairOrderId->get($repairOrder->id))
            ->filter()
            ->values()
            ->all();

        return new JobBoardListPage(
            repairOrders: $repairOrders,
            laneTabs: $laneTabs,
            laneKey: $laneKey,
            query: $query,
            techId: $techId,
            sortKey: $sortKey,
            sortDir: $sortDir,
            queueTotal: $byLane->flatten(1)->count(),
            filteredTotal: $filteredTotal,
            page: $page,
            perPage: $perPage,
            lastPage: $lastPage,
            rows: $rows,
            pageColumns: $pageColumns,
            pageRepairOrders: $pageRepairOrders,
        );
    }

    /**
     * @param  Collection<int, RepairOrder>  $repairOrders
     * @param  list<array{key: string, label: string, tone: string, color: string}>  $laneMeta
     * @return Collection<string, Collection<int, RepairOrder>>
     */
    private function bucketByLane(Collection $repairOrders, array $laneMeta): Collection
    {
        $byLane = collect($laneMeta)
            ->mapWithKeys(fn (array $column): array => [$column['key'] => collect()]);

        foreach ($repairOrders as $repairOrder) {
            $laneKey = WorkboardSwimlaneCatalog::homeBoardColumnKeyForRepairOrder($repairOrder);

            if ($laneKey === null || ! $byLane->has($laneKey)) {
                continue;
            }

            $byLane[$laneKey]->push($repairOrder);
        }

        return $byLane;
    }

    /**
     * Project only the current page into lane columns for surface mapping.
     *
     * @param  Collection<int, RepairOrder>  $pageRepairOrders
     * @param  Collection<string, Collection<int, RepairOrder>>  $byLane
     * @param  list<array{key: string, label: string, tone: string, color: string}>  $laneMeta
     * @return list<WorkboardTriageLaneProjection>
     */
    private function pageColumns(Collection $pageRepairOrders, Collection $byLane, array $laneMeta): array
    {
        if ($pageRepairOrders->isEmpty()) {
            return collect($laneMeta)
                ->map(fn (array $column): WorkboardTriageLaneProjection => new WorkboardTriageLaneProjection(
                    key: $column['key'],
                    label: $column['label'],
                    tone: $column['tone'],
                    totalCount: ($byLane->get($column['key']) ?? collect())->count(),
                    visibleCards: [],
                    hiddenCount: ($byLane->get($column['key']) ?? collect())->count(),
                    viewAllUrl: null,
                    inventoryUrl: WorkboardSwimlaneCatalog::inventoryUrlForHomeColumn($column['key']),
                    color: $column['color'] ?? 'secondary',
                ))
                ->all();
        }

        $cardsById = collect($this->workboardTriage->cardsForRepairOrders($pageRepairOrders))
            ->keyBy(fn (WorkboardTriageCard $card): int => $card->repairOrder->id);

        return collect($laneMeta)
            ->map(function (array $column) use ($pageRepairOrders, $byLane, $cardsById): WorkboardTriageLaneProjection {
                $laneTotal = ($byLane->get($column['key']) ?? collect())->count();
                $visibleCards = $pageRepairOrders
                    ->filter(fn (RepairOrder $repairOrder): bool => WorkboardSwimlaneCatalog::homeBoardColumnKeyForRepairOrder($repairOrder) === $column['key'])
                    ->map(fn (RepairOrder $repairOrder): ?WorkboardTriageCard => $cardsById->get($repairOrder->id))
                    ->filter()
                    ->values()
                    ->all();

                return new WorkboardTriageLaneProjection(
                    key: $column['key'],
                    label: $column['label'],
                    tone: $column['tone'],
                    totalCount: $laneTotal,
                    visibleCards: $visibleCards,
                    hiddenCount: max(0, $laneTotal - count($visibleCards)),
                    viewAllUrl: null,
                    inventoryUrl: WorkboardSwimlaneCatalog::inventoryUrlForHomeColumn($column['key']),
                    color: $column['color'] ?? 'secondary',
                );
            })
            ->all();
    }

    private function matchesSearch(RepairOrder $repairOrder, string $query): bool
    {
        if ($query === '') {
            return true;
        }

        $haystack = Str::lower(collect([
            $repairOrder->repair_order_id,
            $repairOrder->customer?->name,
            $repairOrder->vehicle?->display_name,
            $repairOrder->concern_summary,
            $repairOrder->statusDisplayLabel(),
            $repairOrder->technicianOwnershipLabel(),
        ])->filter()->join(' '));

        return str_contains($haystack, Str::lower($query));
    }

    private function matchesTech(RepairOrder $repairOrder, string $techId): bool
    {
        if ($techId === '') {
            return true;
        }

        $ownerId = $repairOrder->concerns
            ->flatMap->workGroups
            ->first(fn ($group) => $group->hasOwner())
            ?->owner_user_id;

        if ($ownerId !== null && (string) $ownerId === $techId) {
            return true;
        }

        return (string) ($repairOrder->assigned_technician_id ?? '') === $techId
            || (string) ($repairOrder->assignedTechnician?->id ?? '') === $techId;
    }

    /**
     * @param  Collection<int, RepairOrder>  $repairOrders
     * @return Collection<int, RepairOrder>
     */
    private function sortRepairOrders(Collection $repairOrders, string $sortKey, string $sortDir): Collection
    {
        $callback = function (RepairOrder $repairOrder) use ($sortKey): string|int {
            return match ($sortKey) {
                JobBoardListPage::SORT_RO => (string) $repairOrder->repair_order_id,
                JobBoardListPage::SORT_CUSTOMER => Str::lower((string) ($repairOrder->customer?->name ?? '')),
                JobBoardListPage::SORT_VEHICLE => Str::lower((string) ($repairOrder->vehicle?->display_name ?? '')),
                JobBoardListPage::SORT_STATUS => Str::lower($repairOrder->statusDisplayLabel()),
                default => $repairOrder->updated_at?->getTimestamp() ?? 0,
            };
        };

        $sorted = $sortDir === 'desc'
            ? $repairOrders->sortByDesc($callback)
            : $repairOrders->sortBy($callback);

        return $sorted->values();
    }
}
