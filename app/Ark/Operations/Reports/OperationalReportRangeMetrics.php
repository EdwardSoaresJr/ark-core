<?php

namespace App\Ark\Operations\Reports;

use App\Ark\Operations\Financial\LedgerEntryType;
use App\Ark\Operations\Financial\RepairOrderLedgerEntry;
use App\Ark\Operations\Labor\ShopOverheadSnapshot;
use App\Ark\Operations\RepairOrders\PartProcurementState;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\ShopExcellence\ShopExcellenceTargets;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Brick\Money\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OperationalReportRangeMetrics
{
    public function __construct(
        private readonly Carbon $from,
        private readonly Carbon $to,
    ) {}

    /**
     * @return list<array{label: string, value: string, hint: string, tone: 'good'|'warn'|null}>
     */
    public function kpis(): array
    {
        $targets = ShopExcellenceTargets::current();
        $carCount = $this->carCount();
        $postedCount = $this->postedCount();
        $salesPostedCents = $this->postedSalesCents();
        $cashCollectedCents = $this->cashCollectedCents();
        $totalValueCents = $this->totalValueCents();
        $aroCents = $carCount > 0 ? (int) round($totalValueCents / $carCount) : 0;
        $approvedRoCount = $this->approvedRepairOrderCount();
        $unpaidPickupCount = $this->unpaidPickupCount();
        $unpaidPickupCents = $this->unpaidPickupCents();
        $laborSoldCents = $this->laborSoldCents();
        $laborHours = $this->laborHours();
        $partsGpCents = $this->partsGrossProfitCents();
        $partsSalesCents = $this->partsSalesCents();
        $feesSoldCents = $this->feesSoldCents();
        $partsMarginPercent = $this->marginPercent($partsGpCents, $partsSalesCents);
        $laborCostCents = $this->laborCostCents();
        $laborGpCents = $laborSoldCents - $laborCostCents;
        $laborMarginPercent = $this->marginPercent($laborGpCents, $laborSoldCents);
        $effectiveLaborRateCents = $laborHours > 0 ? (int) round($laborSoldCents / $laborHours) : null;
        $mix = $this->partsLaborMixLabel($laborSoldCents, $partsSalesCents);
        $laborMixPercent = $this->laborMixPercent($laborSoldCents, $partsSalesCents);
        $deferredCents = $this->deferredOpportunityCents();
        $elrFloor = $targets['effective_labor_rate_floor_cents'] ?? $targets['posted_labor_rate_cents'];

        return [
            $this->kpi('Sales Posted', $this->money($salesPostedCents), $postedCount.' posted · Tekmetric EOD'),
            $this->kpi('Cash Collected', $this->money($cashCollectedCents), 'payments + deposits · Tekmetric cashiered'),
            $this->kpi('Car Count', (string) $carCount, 'ROs opened in range'),
            $this->kpi(
                'ARO',
                $this->money($aroCents),
                'open + closed value · target '.$this->money($targets['aro_target_cents']),
                ShopExcellenceTargets::toneForMinimum($aroCents, $targets['aro_target_cents']),
            ),
            $this->kpi(
                'Approval Rate',
                $carCount > 0 ? (string) round(($approvedRoCount / $carCount) * 100).'%' : '0%',
                $approvedRoCount.' with approved work',
            ),
            $this->kpi('Unpaid Pickups', $this->money($unpaidPickupCents), $unpaidPickupCount.' awaiting collection'),
            $this->kpi('Labor Sold', $this->money($laborSoldCents), number_format($laborHours, 1).' billed hours'),
            $this->kpi('Parts Sold', $this->money($partsSalesCents), 'posted parts revenue'),
            $this->kpi(
                'Effective Labor Rate',
                $effectiveLaborRateCents !== null ? $this->money($effectiveLaborRateCents).'/hr' : 'n/a',
                $elrFloor !== null
                    ? 'posted sales · ELR floor '.$this->money($elrFloor).'/hr'
                    : 'posted sales / billed hours',
                ShopExcellenceTargets::toneForMinimum($effectiveLaborRateCents, $elrFloor),
            ),
            $this->kpi(
                'Parts Margin',
                $partsMarginPercent !== null ? $partsMarginPercent.'%' : 'n/a',
                'target '.$targets['parts_margin_target_percent'].'% · known costs',
                ShopExcellenceTargets::toneForMinimumPercent($partsMarginPercent, $targets['parts_margin_target_percent']),
            ),
            $this->kpi(
                'Labor Margin',
                $laborMarginPercent !== null ? $laborMarginPercent.'%' : 'n/a',
                $laborCostCents > 0 ? 'closed labor GP' : 'assign labor cost for margin',
            ),
            $this->kpi(
                'Parts/Labor Mix',
                $mix,
                'target '.$targets['parts_sales_target_percent'].'/'.$targets['labor_sales_target_percent'].' sales',
                ShopExcellenceTargets::toneForMixPercent($laborMixPercent, $targets['labor_sales_target_percent']),
            ),
            $this->kpi('Parts GP', $this->money($partsGpCents), 'known part costs only'),
            $this->kpi('Fees Sold', $this->money($feesSoldCents), 'shop fees and supplies on posted ROs'),
            $this->kpi('Deferred Opportunity', $this->money($deferredCents), 'deferred work'),
        ];
    }

    /**
     * Owner day-review KPIs — executive pulse plus margin, pipeline, and break-even supplements.
     *
     * @return list<array{label: string, value: string, hint: string, tone: 'good'|'warn'|null}>
     */
    public function dayReviewKpis(): array
    {
        $partsGpCents = $this->partsGrossProfitCents();
        $partsSalesCents = $this->partsSalesCents();
        $laborSoldCents = $this->laborSoldCents();
        $laborCostCents = $this->laborCostCents();
        $laborGpCents = $laborSoldCents - $laborCostCents;
        $closedGpCents = $partsGpCents + $laborGpCents;
        $salesPostedCents = $this->postedSalesCents();
        $grossMarginPercent = $this->marginPercent($closedGpCents, $salesPostedCents);
        $backorderedCount = $this->backorderedRepairOrderCount();
        $deferredRoCount = $this->dispositionRepairOrderCount(RepairOrderConcernDisposition::Deferred);
        $efficiencyPercent = $this->shopEfficiencyPercent();
        $breakEven = $this->breakEvenSummary();
        $targets = ShopExcellenceTargets::current();
        $postedCount = $this->postedCount();
        $postedRateCents = $targets['posted_labor_rate_cents'];

        $supplemental = [
            $this->kpi(
                'Closed GP',
                $this->money($closedGpCents),
                'closed labor + known parts GP',
            ),
            $this->kpi(
                'Gross Margin',
                $grossMarginPercent !== null ? $grossMarginPercent.'%' : 'n/a',
                $salesPostedCents > 0 ? 'posted GP / posted sales' : 'no posted sales in range',
            ),
            $this->kpi(
                'Labor GP',
                $this->money($laborGpCents),
                $laborCostCents > 0 ? 'closed labor gross profit' : 'assign labor cost for GP',
            ),
            $this->kpi(
                'RO Pipeline',
                $this->money($this->totalValueCents()),
                $this->carCount().' ROs · open + closed value',
            ),
            $this->kpi(
                'Backordered ROs',
                (string) $backorderedCount,
                $backorderedCount > 0 ? 'approved parts waiting on vendor' : 'no procurement blockers',
            ),
            $this->kpi(
                'Deferred ROs',
                (string) $deferredRoCount,
                $deferredRoCount > 0 ? 'ROs with deferred concerns' : 'no deferred write-ups',
            ),
            $this->kpi(
                'Shop Efficiency',
                $efficiencyPercent !== null ? $efficiencyPercent.'%' : 'n/a',
                $efficiencyPercent !== null ? 'closed billed hours / tech capacity' : 'no shop open days in range',
            ),
        ];

        if ($breakEven !== null) {
            $supplemental[] = $this->kpi(
                'Break-even Gap',
                $breakEven['surplus_label'],
                $breakEven['posture'],
                $breakEven['tone'],
            );
            $supplemental[] = $this->kpi(
                'Prorated Fixed',
                $breakEven['prorated_fixed_label'],
                $breakEven['range_days'].' days · monthly '.$breakEven['monthly_fixed_label'],
            );
        } else {
            $supplemental[] = $this->kpi(
                'Break-even Gap',
                'n/a',
                'Add monthly fixed costs in Owner Targets',
            );
        }

        $supplemental[] = $this->kpi(
            'Closed ROs',
            (string) $postedCount,
            $postedCount > 0 ? 'posted sales in range' : 'no posted ROs in range',
        );
        $supplemental[] = $this->kpi(
            'Posted Rate',
            $postedRateCents !== null ? $this->money($postedRateCents).'/hr' : 'n/a',
            'posted labor rate · Owner Targets',
        );

        if ($breakEven !== null && $breakEven['monthly_break_even_sales_label'] !== null) {
            $supplemental[] = $this->kpi(
                'Monthly Break-even',
                $breakEven['monthly_break_even_sales_label'],
                'closed sales needed at current GP margin',
            );
        }

        return array_merge($this->kpis(), $supplemental);
    }

    /** @deprecated Use dayReviewKpis() */
    public function bookendKpis(): array
    {
        return $this->dayReviewKpis();
    }


    /**
     * @return array{label: string, value: string, hint: string, tone: 'good'|'warn'|null}
     */
    private function kpi(string $label, string $value, string $hint, ?string $tone = null): array
    {
        return [
            'label' => $label,
            'value' => $value,
            'hint' => $hint,
            'tone' => $tone,
        ];
    }

    private function marginPercent(int $gpCents, int $salesCents): ?int
    {
        if ($salesCents <= 0) {
            return null;
        }

        return (int) round(($gpCents / $salesCents) * 100);
    }

    private function laborMixPercent(int $laborSalesCents, int $partsSalesCents): ?int
    {
        $total = $laborSalesCents + $partsSalesCents;

        if ($total <= 0) {
            return null;
        }

        return (int) round(($laborSalesCents / $total) * 100);
    }

    private function partsLaborMixLabel(int $laborSalesCents, int $partsSalesCents): string
    {
        $laborPercent = $this->laborMixPercent($laborSalesCents, $partsSalesCents);
        $partsPercent = $laborPercent !== null ? 100 - $laborPercent : null;

        if ($laborPercent === null || $partsPercent === null) {
            return 'n/a';
        }

        return $partsPercent.'% parts · '.$laborPercent.'% labor';
    }

    /**
     * @return list<array{category: string, sales: string, cost: string, gp: string, margin: string}>
     */
    public function financialRows(): array
    {
        $rows = OperationalReportTotals::soldLineQuery()
            ->join('repair_orders', 'repair_orders.id', '=', 'repair_order_lines.repair_order_id')
            ->tap(fn (Builder $query): Builder => OperationalReportDateScope::applySalesClosedBetweenOnJoinedRepairOrders($query, $this->from, $this->to))
            ->groupBy('repair_order_lines.type')
            ->select('repair_order_lines.type')
            ->selectRaw('COALESCE(SUM(repair_order_lines.total_cents), 0) as sales_cents')
            ->selectRaw('COALESCE(SUM(part_cost_cents), 0) as cost_cents')
            ->get()
            ->keyBy('type');

        return collect([
            'Labor' => RepairOrderLineType::Labor,
            'Parts' => RepairOrderLineType::Part,
            'Fees' => RepairOrderLineType::Fee,
        ])->map(function (RepairOrderLineType $type, string $category) use ($rows): array {
            $row = $rows->get($type->value);
            $salesCents = (int) ($row->sales_cents ?? 0);
            $costCents = match ($type) {
                RepairOrderLineType::Part => (int) ($row->cost_cents ?? 0),
                RepairOrderLineType::Labor => $this->laborCostCents(),
                default => 0,
            };
            $gpCents = $salesCents - $costCents;

            return [
                'category' => $category,
                'sales' => $this->money($salesCents),
                'cost' => $costCents > 0 ? $this->money($costCents) : 'n/a',
                'gp' => $this->money($gpCents),
                'margin' => $salesCents > 0 ? (string) round(($gpCents / $salesCents) * 100).'%' : '0%',
            ];
        })->values()->all();
    }

    /**
     * @return list<array{advisor: string, ro_count: int, value: string, approvals: int, deferred: string, unpaid: string}>
     */
    public function advisorRows(): array
    {
        $repairOrders = OperationalReportDateScope::openedBetween(
            RepairOrder::query()->with([
                'encounter.creator',
                'estimateDocuments.creator',
                'concerns.lines',
            ]),
            $this->from,
            $this->to,
        )->get();

        if ($repairOrders->isEmpty()) {
            return [];
        }

        $totalsByRepairOrderId = OperationalReportTotals::totalCentsByRepairOrderId($repairOrders->pluck('id'));
        $unpaidByRepairOrderId = $this->unpaidReadyPickupRepairOrders()
            ->keyBy('id');

        return $repairOrders
            ->groupBy(fn (RepairOrder $repairOrder): string => $repairOrder->serviceAdvisorName() ?? 'Unassigned')
            ->map(function (Collection $group, string $advisor) use ($totalsByRepairOrderId, $unpaidByRepairOrderId): array {
                $valueCents = (int) $group->sum(
                    fn (RepairOrder $repairOrder): int => (int) ($totalsByRepairOrderId[$repairOrder->id] ?? 0),
                );

                $approvals = $group->filter(
                    fn (RepairOrder $repairOrder): bool => $repairOrder->concerns->contains(
                        fn ($concern): bool => $concern->disposition === RepairOrderConcernDisposition::Approved,
                    ),
                )->count();

                $deferredCents = (int) $group->sum(function (RepairOrder $repairOrder): int {
                    return (int) $repairOrder->concerns
                        ->where('disposition', RepairOrderConcernDisposition::Deferred)
                        ->sum(fn ($concern): int => (int) $concern->lines->sum('subtotal_cents'));
                });

                $unpaidCents = (int) $group
                    ->filter(fn (RepairOrder $repairOrder): bool => $unpaidByRepairOrderId->has($repairOrder->id))
                    ->sum(fn (RepairOrder $repairOrder): int => (int) ($totalsByRepairOrderId[$repairOrder->id] ?? 0));

                return [
                    'advisor' => $advisor,
                    'ro_count' => $group->count(),
                    'value' => $this->money($valueCents),
                    'approvals' => $approvals,
                    'deferred' => $this->money($deferredCents),
                    'unpaid' => $this->money($unpaidCents),
                ];
            })
            ->sortByDesc('ro_count')
            ->values()
            ->all();
    }

    /**
     * @return list<array{bucket: string, count: int, value: string, next_action: string}>
     */
    public function opportunityRows(): array
    {
        $deferredCount = $this->dispositionRepairOrderCount(RepairOrderConcernDisposition::Deferred);
        $backorderedCount = $this->backorderedRepairOrderCount();

        return [
            [
                'bucket' => 'Deferred work',
                'count' => $deferredCount,
                'value' => $this->money($this->deferredOpportunityCents(RepairOrderConcernDisposition::Deferred)),
                'next_action' => 'Keep visible in customer/vehicle history',
            ],
            [
                'bucket' => 'Backordered parts',
                'count' => $backorderedCount,
                'value' => 'Vendor dependency',
                'next_action' => 'Confirm ETA before bay scheduling',
            ],
        ];
    }

    /**
     * @return Collection<int, array{repair_order_id: int, customer: string, vehicle: string, total: string, closed_at: Carbon|null}>
     */
    public function recentClosures(): Collection
    {
        $closures = $this->postedSalesRepairOrdersQuery()
            ->with([
                'customer:id,first_name,last_name',
                'vehicle:id,year,make,model',
            ])
            ->orderByDesc('posted_at')
            ->latest('id')
            ->limit(8)
            ->get();

        $totalsByRepairOrderId = OperationalReportTotals::soldTotalCentsByRepairOrderId($closures->pluck('id'));

        return $closures->map(fn (RepairOrder $repairOrder): array => [
            'repair_order_id' => $repairOrder->repair_order_id,
            'customer' => $repairOrder->customer->name,
            'vehicle' => $repairOrder->vehicle->display_name,
            'total' => $this->money((int) ($totalsByRepairOrderId[$repairOrder->id] ?? 0)),
            'closed_at' => $repairOrder->posted_at,
        ])->values();
    }

    /**
     * @param  Collection<int, RepairOrder>  $activeRepairOrders
     * @param  Collection<int, int>  $activeLaborCentsByRepairOrder
     * @return list<array{technician: string, assigned: int, active: int, labor: string, hours: string, closed_hours: string, efficiency: string, efficiency_hint: string, blockers: int}>
     */
    public function technicianRows(Collection $activeRepairOrders, Collection $activeLaborCentsByRepairOrder): array
    {
        $closedHoursByTechnician = OperationalReportTotals::closedLaborHoursByTechnicianId($this->from, $this->to);
        $closedLaborCentsByTechnician = OperationalReportTotals::closedLaborSalesCentsByTechnicianId($this->from, $this->to);
        $openDays = OperationalReportDateScope::shopOpenDayCount($this->from, $this->to);

        $technicianIds = $closedHoursByTechnician->keys()
            ->merge($activeRepairOrders->pluck('assigned_technician_id')->filter())
            ->unique()
            ->values();

        $technicians = User::query()
            ->active()
            ->whereHas('roles', fn ($query) => $query->where('name', ArkRole::Technician->value))
            ->orderBy('name')
            ->get(['id', 'name', 'workday_hours']);

        $technicianIds = $technicianIds
            ->merge($technicians->pluck('id'))
            ->unique()
            ->values();

        $activeByTechnicianId = $activeRepairOrders
            ->filter(fn (RepairOrder $repairOrder): bool => $repairOrder->assigned_technician_id !== null)
            ->groupBy('assigned_technician_id');

        $techniciansById = $technicians->keyBy('id');

        $rows = $technicianIds
            ->map(function (int $technicianId) use (
                $activeByTechnicianId,
                $activeLaborCentsByRepairOrder,
                $closedHoursByTechnician,
                $closedLaborCentsByTechnician,
                $techniciansById,
                $openDays,
            ): ?array {
                $technician = $techniciansById->get($technicianId)
                    ?? User::query()->find($technicianId, ['id', 'name', 'workday_hours']);

                if ($technician === null) {
                    return null;
                }

                $queueOrders = $activeByTechnicianId->get($technicianId, collect());
                $closedHours = (float) ($closedHoursByTechnician[$technicianId] ?? 0);
                $capacityHours = $openDays > 0 ? $openDays * $technician->effectiveWorkdayHours() : 0.0;
                $efficiencyPercent = $capacityHours > 0
                    ? (int) round(($closedHours / $capacityHours) * 100)
                    : null;

                return [
                    'technician' => $technician->name,
                    'assigned' => $queueOrders->count(),
                    'active' => $queueOrders->where('status', RepairOrderStatus::InProgress)->count(),
                    'labor' => $this->money((int) ($closedLaborCentsByTechnician[$technicianId] ?? 0)),
                    'hours' => $this->formatHours($this->queueLaborHours($queueOrders)),
                    'closed_hours' => $this->formatHours($closedHours),
                    'efficiency' => $efficiencyPercent !== null ? $efficiencyPercent.'%' : 'n/a',
                    'efficiency_hint' => $capacityHours > 0
                        ? $this->formatHours($closedHours).' billed / '.$this->formatHours($capacityHours).' hr capacity'
                        : 'No shop open days in range',
                    'blockers' => $queueOrders->filter(fn (RepairOrder $repairOrder): bool => $repairOrder->hasUnresolvedApprovedParts())->count(),
                ];
            })
            ->filter()
            ->sortBy('technician')
            ->values();

        $unassignedOrders = $activeRepairOrders->whereNull('assigned_technician_id');

        if ($unassignedOrders->isNotEmpty()) {
            $rows->push([
                'technician' => 'Unassigned',
                'assigned' => $unassignedOrders->count(),
                'active' => $unassignedOrders->where('status', RepairOrderStatus::InProgress)->count(),
                'labor' => $this->money((int) $unassignedOrders->sum(
                    fn (RepairOrder $repairOrder): int => (int) ($activeLaborCentsByRepairOrder[$repairOrder->id] ?? 0),
                )),
                'hours' => $this->formatHours($this->queueLaborHours($unassignedOrders)),
                'closed_hours' => '0.0',
                'efficiency' => 'n/a',
                'efficiency_hint' => 'Assign technician on closed ROs for efficiency',
                'blockers' => $unassignedOrders->filter(fn (RepairOrder $repairOrder): bool => $repairOrder->hasUnresolvedApprovedParts())->count(),
            ]);
        }

        if ($rows->isEmpty()) {
            return [[
                'technician' => 'Unassigned',
                'assigned' => 0,
                'active' => 0,
                'labor' => '$0.00',
                'hours' => '0.0',
                'closed_hours' => '0.0',
                'efficiency' => 'n/a',
                'efficiency_hint' => 'No technician production in range',
                'blockers' => 0,
            ]];
        }

        return $rows->all();
    }

    /**
     * @param  Collection<int, RepairOrder>  $repairOrders
     */
    private function queueLaborHours(Collection $repairOrders): float
    {
        return (float) $repairOrders
            ->flatMap(fn (RepairOrder $repairOrder): Collection => $repairOrder->lines)
            ->filter(fn (RepairOrderLine $line): bool => $line->type === RepairOrderLineType::Labor)
            ->sum(fn (RepairOrderLine $line): float => (float) $line->quantity);
    }

    private function formatHours(float $hours): string
    {
        return number_format($hours, 1);
    }

    private function carCount(): int
    {
        return OperationalReportDateScope::openedBetween(RepairOrder::query(), $this->from, $this->to)->count();
    }

    private function postedCount(): int
    {
        return $this->postedSalesRepairOrdersQuery()->count();
    }

    private function postedSalesCents(): int
    {
        return OperationalReportTotals::postedSalesCents(
            $this->postedSalesRepairOrdersQuery()->pluck('id'),
        );
    }

    private function cashCollectedCents(): int
    {
        return OperationalReportTotals::cashCollectedCents($this->from, $this->to);
    }

    /**
     * Posted sales in range (Tekmetric End of Day RO summary).
     *
     * @return Builder<RepairOrder>
     */
    private function postedSalesRepairOrdersQuery(): Builder
    {
        return OperationalReportDateScope::salesPostedBetween(RepairOrder::query(), $this->from, $this->to);
    }

    /** @deprecated Use postedSalesRepairOrdersQuery() */
    private function completedClosedRepairOrdersQuery(): Builder
    {
        return $this->postedSalesRepairOrdersQuery();
    }

    /** @deprecated Use postedSalesCents() */
    private function closedSalesCents(): int
    {
        return $this->postedSalesCents();
    }

    /** @deprecated Use postedCount() */
    private function closedCount(): int
    {
        return $this->postedCount();
    }

    private function totalValueCents(): int
    {
        $repairOrderIds = OperationalReportDateScope::openedBetween(
            RepairOrder::query(),
            $this->from,
            $this->to,
        )->pluck('id');

        return OperationalReportTotals::sumTotalCents($repairOrderIds);
    }

    private function approvedRepairOrderCount(): int
    {
        return (int) OperationalReportDateScope::openedBetween(RepairOrder::query(), $this->from, $this->to)
            ->whereHas('concerns', fn ($query) => $query->where('disposition', RepairOrderConcernDisposition::Approved))
            ->count();
    }

    private function unpaidPickupCount(): int
    {
        return $this->unpaidReadyPickupRepairOrders()->count();
    }

    private function unpaidPickupCents(): int
    {
        return OperationalReportTotals::sumTotalCents(
            $this->unpaidReadyPickupRepairOrders()->pluck('id'),
        );
    }

    /**
     * @return Collection<int, RepairOrder>
     */
    private function unpaidReadyPickupRepairOrders(): Collection
    {
        return OperationalReportDateScope::openedBetween(RepairOrder::query(), $this->from, $this->to)
            ->where('status', RepairOrderStatus::ReadyPickup)
            ->get()
            ->filter(fn (RepairOrder $repairOrder): bool => ! $repairOrder->isPaid())
            ->values();
    }

    private function laborSoldCents(): int
    {
        return (int) OperationalReportTotals::soldLineQuery()
            ->join('repair_orders', 'repair_orders.id', '=', 'repair_order_lines.repair_order_id')
            ->tap(fn (Builder $query): Builder => OperationalReportDateScope::applySalesClosedBetweenOnJoinedRepairOrders($query, $this->from, $this->to))
            ->where('repair_order_lines.type', RepairOrderLineType::Labor)
            ->sum('repair_order_lines.subtotal_cents');
    }

    private function laborHours(): float
    {
        return (float) OperationalReportTotals::soldLineQuery()
            ->join('repair_orders', 'repair_orders.id', '=', 'repair_order_lines.repair_order_id')
            ->tap(fn (Builder $query): Builder => OperationalReportDateScope::applySalesClosedBetweenOnJoinedRepairOrders($query, $this->from, $this->to))
            ->where('repair_order_lines.type', RepairOrderLineType::Labor)
            ->sum('repair_order_lines.quantity');
    }

    private function partsSalesCents(): int
    {
        return (int) OperationalReportTotals::soldLineQuery()
            ->join('repair_orders', 'repair_orders.id', '=', 'repair_order_lines.repair_order_id')
            ->tap(fn (Builder $query): Builder => OperationalReportDateScope::applySalesClosedBetweenOnJoinedRepairOrders($query, $this->from, $this->to))
            ->where('repair_order_lines.type', RepairOrderLineType::Part)
            ->sum('repair_order_lines.subtotal_cents');
    }

    private function feesSoldCents(): int
    {
        $totals = OperationalReportTotals::soldLineQuery()
            ->join('repair_orders', 'repair_orders.id', '=', 'repair_order_lines.repair_order_id')
            ->tap(fn (Builder $query): Builder => OperationalReportDateScope::applySalesClosedBetweenOnJoinedRepairOrders($query, $this->from, $this->to))
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN repair_order_lines.type = ? THEN repair_order_lines.subtotal_cents ELSE 0 END), 0) as fee_line_cents',
                [RepairOrderLineType::Fee->value],
            )
            ->selectRaw('COALESCE(SUM(repair_order_lines.shop_fee_cents), 0) as allocated_shop_fee_cents')
            ->first();

        return (int) ($totals->fee_line_cents ?? 0) + (int) ($totals->allocated_shop_fee_cents ?? 0);
    }

    private function shopEfficiencyPercent(): ?int
    {
        $openDays = OperationalReportDateScope::shopOpenDayCount($this->from, $this->to);

        if ($openDays <= 0) {
            return null;
        }

        $closedHours = (float) OperationalReportTotals::closedLaborHoursByTechnicianId($this->from, $this->to)->sum();

        $capacityHours = (float) User::query()
            ->active()
            ->whereHas('roles', fn ($query) => $query->where('name', ArkRole::Technician->value))
            ->get(['workday_hours'])
            ->sum(fn (User $technician): float => $openDays * $technician->effectiveWorkdayHours());

        if ($capacityHours <= 0) {
            return null;
        }

        return (int) round(($closedHours / $capacityHours) * 100);
    }

    private function partsGrossProfitCents(): int
    {
        return (int) OperationalReportTotals::soldLineQuery()
            ->join('repair_orders', 'repair_orders.id', '=', 'repair_order_lines.repair_order_id')
            ->tap(fn (Builder $query): Builder => OperationalReportDateScope::applySalesClosedBetweenOnJoinedRepairOrders($query, $this->from, $this->to))
            ->where('repair_order_lines.type', RepairOrderLineType::Part)
            ->selectRaw('COALESCE(SUM(subtotal_cents - COALESCE(part_cost_cents, 0)), 0) as gp_cents')
            ->value('gp_cents');
    }

    private function laborCostCents(): int
    {
        return OperationalReportTotals::closedLaborCostCents($this->from, $this->to);
    }

    private function deferredOpportunityCents(?RepairOrderConcernDisposition $disposition = null): int
    {
        $query = RepairOrderLine::query()
            ->join('repair_order_concerns', 'repair_order_concerns.id', '=', 'repair_order_lines.repair_order_concern_id')
            ->join('repair_orders', 'repair_orders.id', '=', 'repair_order_lines.repair_order_id')
            ->tap(fn (Builder $builder): Builder => OperationalReportDateScope::applyOpenedBetweenOnJoinedRepairOrders($builder, $this->from, $this->to));

        if ($disposition !== null) {
            $query->where('repair_order_concerns.disposition', $disposition);
        } else {
            $query->where('repair_order_concerns.disposition', RepairOrderConcernDisposition::Deferred);
        }

        return (int) $query->sum('repair_order_lines.subtotal_cents');
    }

    private function dispositionRepairOrderCount(RepairOrderConcernDisposition $disposition): int
    {
        return OperationalReportDateScope::openedBetween(RepairOrder::query(), $this->from, $this->to)
            ->whereHas('concerns', fn ($query) => $query->where('disposition', $disposition))
            ->count();
    }

    private function backorderedRepairOrderCount(): int
    {
        return OperationalReportDateScope::openedBetween(RepairOrder::query(), $this->from, $this->to)
            ->whereExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('repair_order_lines')
                    ->join('repair_order_concerns', 'repair_order_concerns.id', '=', 'repair_order_lines.repair_order_concern_id')
                    ->whereColumn('repair_order_lines.repair_order_id', 'repair_orders.id')
                    ->where('repair_order_lines.type', RepairOrderLineType::Part)
                    ->where('repair_order_lines.procurement_state', PartProcurementState::Backordered)
                    ->where('repair_order_concerns.disposition', RepairOrderConcernDisposition::Approved);
            })
            ->count();
    }

    /**
     * Margin health rows — closed sales truth vs shop targets.
     *
     * @return list<array{metric: string, actual: string, target: string, posture: string, tone: 'good'|'warn'|null, action: string}>
     */
    public function marginHealthRows(): array
    {
        $targets = ShopExcellenceTargets::current();
        $carCount = $this->carCount();
        $totalValueCents = $this->totalValueCents();
        $aroCents = $carCount > 0 ? (int) round($totalValueCents / $carCount) : 0;
        $laborSoldCents = $this->laborSoldCents();
        $laborHours = $this->laborHours();
        $partsGpCents = $this->partsGrossProfitCents();
        $partsSalesCents = $this->partsSalesCents();
        $partsMarginPercent = $this->marginPercent($partsGpCents, $partsSalesCents);
        $laborCostCents = $this->laborCostCents();
        $laborGpCents = $laborSoldCents - $laborCostCents;
        $laborMarginPercent = $this->marginPercent($laborGpCents, $laborSoldCents);
        $effectiveLaborRateCents = $laborHours > 0 ? (int) round($laborSoldCents / $laborHours) : null;
        $laborMixPercent = $this->laborMixPercent($laborSoldCents, $partsSalesCents);
        $partsMixPercent = $laborMixPercent !== null ? 100 - $laborMixPercent : null;
        $postedRateCents = $targets['posted_labor_rate_cents'];
        $elrFloor = $targets['effective_labor_rate_floor_cents'] ?? $postedRateCents;

        $rows = [
            $this->marginHealthRow(
                'Average repair order',
                $carCount > 0 ? $this->money($aroCents) : 'n/a',
                $this->money($targets['aro_target_cents']),
                $carCount > 0 ? $this->compareCentsPosture($aroCents, $targets['aro_target_cents']) : 'No ROs opened in range',
                ShopExcellenceTargets::toneForMinimum($carCount > 0 ? $aroCents : null, $targets['aro_target_cents']),
                'Better inspections and write-up discipline raise ARO without more bays',
            ),
            $this->marginHealthRow(
                'Effective labor rate',
                $effectiveLaborRateCents !== null ? $this->money($effectiveLaborRateCents).'/hr' : 'n/a',
                $elrFloor !== null ? $this->money($elrFloor).'/hr floor' : 'Set posted rate in targets',
                $this->elrPosture($effectiveLaborRateCents, $postedRateCents, $elrFloor),
                ShopExcellenceTargets::toneForMinimum($effectiveLaborRateCents, $elrFloor),
                'Charge for all diag time; stop free inspections and underpriced menus',
            ),
            $this->marginHealthRow(
                'Posted labor rate',
                $postedRateCents !== null ? $this->money($postedRateCents).'/hr' : 'n/a',
                'Shop Settings default labor category',
                $postedRateCents !== null ? 'Authoritative posted rate' : 'Configure under Financial Rules → Labor',
                null,
                'Raise in small steps at least once per year',
            ),
            $this->marginHealthRow(
                'Parts margin',
                $partsMarginPercent !== null ? $partsMarginPercent.'%' : 'n/a',
                $targets['parts_margin_target_percent'].'%',
                $this->comparePercentPosture($partsMarginPercent, $targets['parts_margin_target_percent']),
                ShopExcellenceTargets::toneForMinimumPercent($partsMarginPercent, $targets['parts_margin_target_percent']),
                'Follow the parts matrix — advisors do not discount margin away',
            ),
            $this->marginHealthRow(
                'Labor margin',
                $laborMarginPercent !== null ? $laborMarginPercent.'%' : 'n/a',
                'Loaded cost model',
                $laborCostCents > 0 ? 'Closed labor GP on assigned tech cost' : 'Assign labor cost on staff for margin',
                null,
                'Staff Settings → loaded labor cost per technician',
            ),
            $this->marginHealthRow(
                'Parts / labor sales mix',
                $partsMixPercent !== null && $laborMixPercent !== null
                    ? $partsMixPercent.'% / '.$laborMixPercent.'%'
                    : 'n/a',
                $targets['parts_sales_target_percent'].'% / '.$targets['labor_sales_target_percent'].'%',
                $this->mixPosture($laborMixPercent, $targets['labor_sales_target_percent']),
                ShopExcellenceTargets::toneForMixPercent($laborMixPercent, $targets['labor_sales_target_percent']),
                'Balanced shops target ~45% parts / 55% labor on closed sales',
            ),
        ];

        return $rows;
    }

    /**
     * Management P&L from closed RO truth — not bookkeeper P&L.
     *
     * @return array{
     *     range_days: int,
     *     posted_count: int,
     *     configured_fixed_costs: bool,
     *     pl_lines: list<array{
     *         label: string,
     *         amount: string,
     *         percent: string|null,
     *         indent: bool,
     *         emphasis: 'normal'|'subtotal'|'total'|'benchmark',
     *         tone: 'good'|'warn'|null,
     *         note: string|null
     *     }>,
     *     tax_lines: list<array{
     *         label: string,
     *         amount: string,
     *         source: string,
     *         tone: 'good'|'warn'|null,
     *         note: string|null
     *     }>,
     *     benchmark: array{
     *         net_target_percent: int,
     *         net_target_label: string,
     *         estimated_net_label: string|null,
     *         gap_label: string|null,
     *         tone: 'good'|'warn'|null,
     *         posture: string
     *     }|null,
     *     disclaimer: string
     * }
     */
    public function ownerPlSummary(): array
    {
        $targets = ShopExcellenceTargets::current();
        $rangeDays = max(1, (int) $this->from->copy()->startOfDay()->diffInDays($this->to->copy()->startOfDay()) + 1);

        $partsSalesCents = $this->partsSalesCents();
        $partsCostCents = $this->partsCostCents();
        $partsGpCents = $this->partsGrossProfitCents();
        $laborSoldCents = $this->laborSoldCents();
        $laborCostCents = $this->laborCostCents();
        $laborGpCents = $laborSoldCents - $laborCostCents;
        $feesSoldCents = $this->feesSoldCents();
        $serviceRevenueCents = $partsSalesCents + $laborSoldCents + $feesSoldCents;
        $taxCollectedCents = $this->closedTaxCollectedCents();
        $totalCollectedCents = $this->cashCollectedCents();
        $cashOnPostedRepairOrdersCents = OperationalReportTotals::cashCollectedCentsForRepairOrders(
            $this->postedSalesRepairOrdersQuery()->pluck('id'),
            $this->from,
            $this->to,
        );
        $postedSalesCents = $this->postedSalesCents();
        $cogsCents = $partsCostCents + $laborCostCents;
        $grossProfitCents = $partsGpCents + $laborGpCents + $feesSoldCents;
        $grossMarginPercent = $this->marginPercent($grossProfitCents, $serviceRevenueCents);

        $monthlyFixedCents = ShopExcellenceTargets::monthlyFixedCostsCents();
        $monthlyAdvisorPayrollCents = $this->monthlyAdvisorPayrollCents();
        $proratedFixedCents = $monthlyFixedCents !== null && $monthlyFixedCents > 0
            ? $this->proratedMonthlyCents($monthlyFixedCents, $rangeDays)
            : null;
        $proratedAdvisorPayrollCents = $monthlyAdvisorPayrollCents > 0
            ? $this->proratedMonthlyCents($monthlyAdvisorPayrollCents, $rangeDays)
            : 0;
        $proratedShopFixedCents = $proratedFixedCents !== null
            ? max(0, $proratedFixedCents - $proratedAdvisorPayrollCents)
            : null;
        $operatingIncomeCents = $proratedFixedCents !== null
            ? $grossProfitCents - $proratedFixedCents
            : null;

        $payrollTaxReserveCents = $this->proratedPayrollTaxReserveCents($laborCostCents, $rangeDays, $targets);
        $incomeTaxReserveCents = $operatingIncomeCents !== null && $operatingIncomeCents > 0
            ? (int) round($operatingIncomeCents * ($targets['income_tax_reserve_percent'] / 100))
            : 0;
        $estimatedNetCents = $operatingIncomeCents !== null
            ? $operatingIncomeCents - $payrollTaxReserveCents - $incomeTaxReserveCents
            : null;

        $netTargetPercent = (int) $targets['net_profit_target_percent'];
        $netProfitTargetCents = $serviceRevenueCents > 0
            ? (int) round($serviceRevenueCents * ($netTargetPercent / 100))
            : null;
        $netGapCents = $estimatedNetCents !== null && $netProfitTargetCents !== null
            ? $estimatedNetCents - $netProfitTargetCents
            : null;

        $plLines = [
            $this->ownerPlLine('Service revenue (pre-tax)', $serviceRevenueCents, $serviceRevenueCents, false, 'subtotal'),
            $this->ownerPlLine('Parts sales', $partsSalesCents, $serviceRevenueCents, true),
            $this->ownerPlLine('Labor sales', $laborSoldCents, $serviceRevenueCents, true),
            $this->ownerPlLine('Shop fees sold', $feesSoldCents, $serviceRevenueCents, true),
            $this->ownerPlLine('Sales tax collected', $taxCollectedCents, $serviceRevenueCents, true, 'normal', null, 'Pass-through liability — not shop revenue'),
            $this->ownerPlLine('Cost of goods sold', $cogsCents, $serviceRevenueCents, false, 'subtotal'),
            $this->ownerPlLine('Parts cost', $partsCostCents, $serviceRevenueCents, true),
            $this->ownerPlLine('Labor cost (assigned tech loaded rate)', $laborCostCents, $serviceRevenueCents, true, 'normal', null, 'Staff Settings loaded cost × billed hours'),
            $this->ownerPlLine('Gross profit', $grossProfitCents, $serviceRevenueCents, false, 'subtotal', null, $grossMarginPercent !== null ? $grossMarginPercent.'% gross margin on service revenue' : null),
            $this->ownerPlLine('Cash collected', $totalCollectedCents, 0, false, 'normal', null, 'All payments + deposits dated in range — full cash drawer; can include unposted ROs and legacy carryover payments'),
            $this->ownerPlLine('Cash on posted ROs', $cashOnPostedRepairOrdersCents, 0, false, 'normal', null, 'Payments in range tied to ROs posted this period'),
            $this->ownerPlLine('Sales posted', $postedSalesCents, 0, false, 'normal', null, 'Posted invoice totals in range — Tekmetric RO summary'),
        ];

        if ($proratedFixedCents !== null) {
            $plLines[] = $this->ownerPlLine(
                'Operating expenses',
                $proratedFixedCents,
                $serviceRevenueCents,
                false,
                'subtotal',
                null,
                $this->money((int) ($monthlyFixedCents ?? 0)).'/mo from Owner Targets · '.$rangeDays.' days in range',
            );
            $plLines[] = $this->ownerPlLine(
                'Advisor / office payroll',
                $proratedAdvisorPayrollCents,
                $serviceRevenueCents,
                true,
                'normal',
                null,
                $monthlyAdvisorPayrollCents > 0
                    ? 'Shop Overhead → Office and advisor payroll · excludes technician wages'
                    : 'Enter under Settings → Shop Overhead → Payroll row',
            );
            $plLines[] = $this->ownerPlLine(
                'Shop fixed overhead',
                $proratedShopFixedCents ?? 0,
                $serviceRevenueCents,
                true,
                'normal',
                null,
                'Rent, utilities, insurance, software, and other fixed worksheet lines',
            );
            $plLines[] = $this->ownerPlLine(
                'Operating income (est.)',
                $operatingIncomeCents ?? 0,
                $serviceRevenueCents,
                false,
                'total',
                ($operatingIncomeCents ?? 0) >= 0 ? 'good' : 'warn',
            );
        } else {
            $plLines[] = $this->ownerPlLine(
                'Operating expenses',
                0,
                $serviceRevenueCents,
                false,
                'normal',
                'warn',
                'Set monthly fixed costs in Owner Targets to estimate operating income',
            );
        }

        $taxLines = [
            [
                'label' => 'Sales tax to remit',
                'amount' => $this->money($taxCollectedCents),
                'source' => 'Closed RO lines',
                'tone' => $taxCollectedCents > 0 ? null : null,
                'note' => 'Sum of tax_cents on approved sold lines — reconcile with POS / accountant filings',
            ],
            [
                'label' => 'Payroll tax reserve (est.)',
                'amount' => $this->money($payrollTaxReserveCents),
                'source' => ($targets['monthly_payroll_tax_cents'] ?? null) !== null
                    ? 'Owner Targets monthly payroll tax'
                    : $targets['payroll_tax_reserve_percent'].'% of closed labor cost',
                'tone' => null,
                'note' => 'Employer burden estimate — skip if loaded labor cost already includes full burden',
            ],
            [
                'label' => 'Income tax reserve (est.)',
                'amount' => $this->money($incomeTaxReserveCents),
                'source' => $targets['income_tax_reserve_percent'].'% of positive operating income',
                'tone' => null,
                'note' => 'Rough S-corp / owner distribution planning — not a filing amount',
            ],
            [
                'label' => 'Total tax posture (est.)',
                'amount' => $this->money($taxCollectedCents + $payrollTaxReserveCents + $incomeTaxReserveCents),
                'source' => 'Sales + payroll + income reserves',
                'tone' => null,
                'note' => 'Cash to set aside beyond normal AP — confirm with bookkeeper',
            ],
        ];

        $benchmark = $netProfitTargetCents !== null ? [
            'net_target_percent' => $netTargetPercent,
            'net_target_label' => $this->money($netProfitTargetCents),
            'estimated_net_label' => $estimatedNetCents !== null ? $this->money($estimatedNetCents) : null,
            'gap_label' => $netGapCents !== null
                ? (($netGapCents >= 0 ? '+' : '−').$this->money(abs($netGapCents)))
                : null,
            'tone' => $netGapCents !== null ? ($netGapCents >= 0 ? 'good' : 'warn') : null,
            'posture' => $estimatedNetCents === null
                ? 'Configure monthly fixed costs to estimate net vs '.$netTargetPercent.'% target'
                : ($netGapCents !== null && $netGapCents >= 0
                    ? 'At or above '.$netTargetPercent.'% net profit target on service revenue'
                    : ($netGapCents !== null
                        ? $this->money(abs($netGapCents)).' below '.$netTargetPercent.'% net target after tax reserves'
                        : 'No closed service revenue in range')),
        ] : null;

        return [
            'range_days' => $rangeDays,
            'posted_count' => $this->postedCount(),
            'configured_fixed_costs' => $proratedFixedCents !== null,
            'pl_lines' => $plLines,
            'tax_lines' => $taxLines,
            'benchmark' => $benchmark,
            'disclaimer' => 'Management estimate from posted sales. Reconcile operating income and tax lines with your bookkeeper P&L — ARK does not replace accounting.',
        ];
    }

    /**
     * @param  array<string, mixed>  $targets
     */
    private function proratedPayrollTaxReserveCents(int $laborCostCents, int $rangeDays, array $targets): int
    {
        $monthlyPayrollTaxCents = $targets['monthly_payroll_tax_cents'] ?? null;

        if ($monthlyPayrollTaxCents !== null && $monthlyPayrollTaxCents > 0) {
            return $this->proratedMonthlyCents((int) $monthlyPayrollTaxCents, $rangeDays);
        }

        if ($laborCostCents <= 0) {
            return 0;
        }

        return (int) round($laborCostCents * ((int) $targets['payroll_tax_reserve_percent'] / 100));
    }

    private function proratedMonthlyCents(int $monthlyCents, int $rangeDays): int
    {
        return (int) round($monthlyCents * ($rangeDays / 30.437));
    }

    private function monthlyAdvisorPayrollCents(): int
    {
        $snapshot = ShopOverheadSnapshot::fromState(ShopSettings::current()->shopOverheadStateArray());

        return (int) round($snapshot->monthlyOfficePayrollTotal() * 100);
    }

    /**
     * @return array{
     *     label: string,
     *     amount: string,
     *     percent: string|null,
     *     indent: bool,
     *     emphasis: 'normal'|'subtotal'|'total'|'benchmark',
     *     tone: 'good'|'warn'|null,
     *     note: string|null
     * }
     */
    private function ownerPlLine(
        string $label,
        int $amountCents,
        int $revenueCents,
        bool $indent,
        string $emphasis = 'normal',
        ?string $tone = null,
        ?string $note = null,
    ): array {
        return [
            'label' => $label,
            'amount' => $this->money($amountCents),
            'percent' => $this->percentOfRevenueLabel($amountCents, $revenueCents),
            'indent' => $indent,
            'emphasis' => $emphasis,
            'tone' => $tone,
            'note' => $note,
        ];
    }

    private function percentOfRevenueLabel(int $amountCents, int $revenueCents): ?string
    {
        if ($revenueCents <= 0) {
            return null;
        }

        return (int) round(($amountCents / $revenueCents) * 100).'%';
    }

    private function closedTaxCollectedCents(): int
    {
        return (int) OperationalReportTotals::soldLineQuery()
            ->join('repair_orders', 'repair_orders.id', '=', 'repair_order_lines.repair_order_id')
            ->tap(fn (Builder $query): Builder => OperationalReportDateScope::applySalesClosedBetweenOnJoinedRepairOrders($query, $this->from, $this->to))
            ->sum('repair_order_lines.tax_cents');
    }

    private function partsCostCents(): int
    {
        return (int) OperationalReportTotals::soldLineQuery()
            ->join('repair_orders', 'repair_orders.id', '=', 'repair_order_lines.repair_order_id')
            ->tap(fn (Builder $query): Builder => OperationalReportDateScope::applySalesClosedBetweenOnJoinedRepairOrders($query, $this->from, $this->to))
            ->where('repair_order_lines.type', RepairOrderLineType::Part)
            ->selectRaw('COALESCE(SUM(COALESCE(repair_order_lines.part_cost_cents, 0)), 0) as cost_cents')
            ->value('cost_cents');
    }

    /**
     * Prorated fixed-cost coverage from closed labor + parts GP.
     *
     * @return array{
     *     gross_profit_label: string,
     *     prorated_fixed_label: string,
     *     surplus_label: string,
     *     posture: string,
     *     tone: 'good'|'warn',
     *     gross_margin_percent: int|null,
     *     monthly_fixed_label: string,
     *     monthly_break_even_sales_label: string|null,
     *     range_days: int,
     *     action: string
     * }|null
     */
    public function breakEvenSummary(): ?array
    {
        $monthlyFixedCents = ShopExcellenceTargets::monthlyFixedCostsCents();

        if ($monthlyFixedCents === null || $monthlyFixedCents <= 0) {
            return null;
        }

        $rangeDays = max(1, (int) $this->from->copy()->startOfDay()->diffInDays($this->to->copy()->startOfDay()) + 1);
        $proratedFixedCents = (int) round($monthlyFixedCents * ($rangeDays / 30.437));

        $partsGpCents = $this->partsGrossProfitCents();
        $laborGpCents = $this->laborSoldCents() - $this->laborCostCents();
        $grossProfitCents = $partsGpCents + $laborGpCents;
        $salesPostedCents = $this->postedSalesCents();
        $grossMarginPercent = $salesPostedCents > 0
            ? (int) round(($grossProfitCents / $salesPostedCents) * 100)
            : null;

        $surplusCents = $grossProfitCents - $proratedFixedCents;
        $tone = $surplusCents >= 0 ? 'good' : 'warn';
        $posture = $surplusCents >= 0
            ? 'Covering prorated fixed costs on closed GP'
            : $this->money(abs($surplusCents)).' short of prorated fixed costs';

        $monthlyBreakEvenSalesLabel = $grossMarginPercent !== null && $grossMarginPercent > 0
            ? $this->money((int) round($monthlyFixedCents / ($grossMarginPercent / 100)))
            : null;

        return [
            'gross_profit_label' => $this->money($grossProfitCents),
            'prorated_fixed_label' => $this->money($proratedFixedCents),
            'surplus_label' => ($surplusCents >= 0 ? '+' : '−').$this->money(abs($surplusCents)),
            'posture' => $posture,
            'tone' => $tone,
            'gross_margin_percent' => $grossMarginPercent,
            'monthly_fixed_label' => $this->money($monthlyFixedCents),
            'monthly_break_even_sales_label' => $monthlyBreakEvenSalesLabel,
            'range_days' => $rangeDays,
            'action' => $surplusCents >= 0
                ? 'Reconcile with bookkeeper P&L — ARK GP is closed labor + known parts only'
                : 'Raise margin levers or trim fixed costs — see Margin Health rows above',
        ];
    }

    /**
     * @return array{metric: string, actual: string, target: string, posture: string, tone: 'good'|'warn'|null, action: string}
     */
    private function marginHealthRow(
        string $metric,
        string $actual,
        string $target,
        string $posture,
        ?string $tone,
        string $action,
    ): array {
        return [
            'metric' => $metric,
            'actual' => $actual,
            'target' => $target,
            'posture' => $posture,
            'tone' => $tone,
            'action' => $action,
        ];
    }

    private function compareCentsPosture(int $actualCents, int $targetCents): string
    {
        if ($actualCents >= $targetCents) {
            return 'At or above target';
        }

        $gap = $targetCents - $actualCents;

        return $this->money($gap).' below target';
    }

    private function comparePercentPosture(?int $actualPercent, int $targetPercent): string
    {
        if ($actualPercent === null) {
            return 'No closed parts sales in range';
        }

        if ($actualPercent >= $targetPercent) {
            return 'At or above target';
        }

        return ($targetPercent - $actualPercent).' pts below target';
    }

    private function elrPosture(?int $elrCents, ?int $postedCents, ?int $floorCents): string
    {
        if ($elrCents === null) {
            return 'No closed labor hours in range';
        }

        if ($floorCents !== null && $elrCents < $floorCents) {
            return 'Below ELR floor — check free work and menu pricing';
        }

        if ($postedCents !== null && $elrCents < (int) round($postedCents * 0.9)) {
            return 'ELR leak — posted rate not reaching the bank';
        }

        return 'ELR holding near posted rate';
    }

    private function mixPosture(?int $laborPercent, int $laborTargetPercent): string
    {
        if ($laborPercent === null) {
            return 'No closed parts/labor mix in range';
        }

        $delta = abs($laborPercent - $laborTargetPercent);

        if ($delta <= 8) {
            return 'Within target band';
        }

        return $laborPercent < $laborTargetPercent
            ? 'Labor light — matrix or inspection opportunity'
            : 'Parts light — check parts write-up and matrix';
    }

    private function money(int $cents): string
    {
        return '$'.Money::ofMinor($cents, 'USD')->getAmount()->toScale(2)->__toString();
    }
}
