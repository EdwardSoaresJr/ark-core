<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Parts\Contracts\PartsCatalogLauncher;
use App\Ark\Operations\ShopExcellence\ShopExcellenceTargets;
use Illuminate\Support\Collection;

final class RepairOrderEstimateInstrumentProjection
{
    /**
     * @return array{instruments: list<array<string, mixed>>}
     */
    public static function for(RepairOrder $repairOrder, EstimateTotals $totals): array
    {
        return app(self::class)->build($repairOrder, $totals);
    }

    /**
     * @return array{instruments: list<array<string, mixed>>}
     */
    public function build(RepairOrder $repairOrder, EstimateTotals $totals): array
    {
        $repairOrder->loadMissing(['lines.concern', 'concerns', 'assignedTechnician']);

        $billableLines = $this->billableLines($repairOrder->lines);
        $partsMetrics = $this->partsMetrics($billableLines, $totals);
        $laborMetrics = $this->laborMetrics($billableLines, $totals, $repairOrder);
        $serviceRevenueCents = $totals->subtotalBeforeTaxCents();
        $grossProfitCents = $partsMetrics['gp_cents'] + $laborMetrics['gp_cents'] + $totals->feesCents();
        $costCents = $partsMetrics['cost_cents'] + $laborMetrics['cost_cents'];
        $marginPercent = $this->marginPercent($grossProfitCents, $serviceRevenueCents);
        $markupPercent = $this->markupPercent($grossProfitCents, $costCents);
        $marginPosture = $this->marginPosture($marginPercent);
        $procurement = $this->procurementSummary($billableLines, $repairOrder);
        $approval = $this->approvalSummary($repairOrder);
        $laborHours = $this->formatHours($laborMetrics['hours']);
        $warrantyExposure = $this->warrantyExposureSummary($billableLines);

        $customerTotalInspect = $this->customerTotalInspectItems($totals);
        $marginInspect = $this->marginInspectItems(
            $totals,
            $partsMetrics,
            $laborMetrics,
            $serviceRevenueCents,
            $grossProfitCents,
            $marginPercent,
            $markupPercent,
            $marginPosture,
            $warrantyExposure,
        );

        return [
            'instruments' => [
                [
                    'key' => 'total',
                    'label' => 'Customer total',
                    'value' => $totals->format($totals->totalCents()),
                    'badge' => null,
                    'tone' => null,
                    'meter_percent' => null,
                    'inspect' => $customerTotalInspect,
                ],
                [
                    'key' => 'margin',
                    'label' => 'Margin',
                    'value' => $marginPercent !== null ? $marginPercent.'%' : 'n/a',
                    'badge' => $marginPosture['label'],
                    'tone' => $marginPosture['tone'],
                    'meter_percent' => $marginPercent,
                    'inspect' => $marginInspect,
                ],
                [
                    'key' => 'parts',
                    'label' => 'Parts',
                    'value' => $procurement['label'],
                    'badge' => null,
                    'tone' => $procurement['tone'],
                    'meter_percent' => $procurement['meter_percent'],
                    'inspect' => [
                        'title' => 'Parts procurement',
                        'items' => $procurement['items'],
                        'footer' => $procurement['footer'],
                    ],
                ],
                [
                    'key' => 'labor',
                    'label' => 'Labor',
                    'value' => $laborHours,
                    'badge' => null,
                    'tone' => null,
                    'meter_percent' => null,
                    'inspect' => [
                        'title' => 'Labor',
                        'items' => [
                            ['label' => 'Billed hours', 'detail' => $laborHours],
                            ['label' => 'Labor sell', 'detail' => $totals->format($totals->grossLaborCents())],
                            [
                                'label' => 'Labor cost',
                                'detail' => $laborMetrics['cost_cents'] > 0
                                    ? $totals->format($laborMetrics['cost_cents'])
                                    : 'Assign technician cost',
                            ],
                            [
                                'label' => 'Labor margin',
                                'detail' => $laborMetrics['margin_percent'] !== null
                                    ? $laborMetrics['margin_percent'].'%'
                                    : 'n/a',
                            ],
                        ],
                        'footer' => $repairOrder->assignedTechnician?->name
                            ? 'Assigned · '.$repairOrder->assignedTechnician->name
                            : null,
                    ],
                ],
                [
                    'key' => 'approval',
                    'label' => 'Approval',
                    'value' => $approval['value'],
                    'badge' => null,
                    'tone' => $approval['tone'],
                    'meter_percent' => $approval['percent'],
                    'inspect' => [
                        'title' => 'Customer approval',
                        'items' => $approval['items'],
                        'footer' => $approval['footer'],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  Collection<int, RepairOrderLine>  $lines
     * @return Collection<int, RepairOrderLine>
     */
    private function billableLines(Collection $lines): Collection
    {
        $hasApprovedWork = $lines->contains(
            fn (RepairOrderLine $line): bool => $line->concern?->disposition === RepairOrderConcernDisposition::Approved,
        );

        return $lines
            ->filter(function (RepairOrderLine $line) use ($hasApprovedWork): bool {
                if (! $line->shouldDisplayOnEstimateWorksheet() || $line->type->isNote()) {
                    return false;
                }

                $disposition = $line->concern?->disposition;

                return $disposition === null || $disposition->countsTowardEstimateTotal($hasApprovedWork);
            })
            ->values();
    }

    /**
     * @param  Collection<int, RepairOrderLine>  $billableLines
     * @return array{sales_cents: int, cost_cents: int, gp_cents: int, margin_percent: int|null}
     */
    private function partsMetrics(Collection $billableLines, EstimateTotals $totals): array
    {
        $partLines = $billableLines->filter(fn (RepairOrderLine $line): bool => $line->isPart());

        $salesCents = $totals->grossPartsCents();
        $costCents = (int) $partLines->sum(function (RepairOrderLine $line): int {
            if ($line->part_cost_cents === null) {
                return 0;
            }

            return (int) round(((float) $line->quantity) * $line->part_cost_cents);
        });
        $gpCents = $salesCents - $costCents;

        return [
            'sales_cents' => $salesCents,
            'cost_cents' => $costCents,
            'gp_cents' => $gpCents,
            'margin_percent' => $this->marginPercent($gpCents, $salesCents),
        ];
    }

    /**
     * @param  Collection<int, RepairOrderLine>  $billableLines
     * @return array{sales_cents: int, cost_cents: int, gp_cents: int, margin_percent: int|null, hours: float}
     */
    private function laborMetrics(Collection $billableLines, EstimateTotals $totals, RepairOrder $repairOrder): array
    {
        $laborLines = $billableLines->filter(
            fn (RepairOrderLine $line): bool => $line->type->countsTowardFlagHours(),
        );

        $salesCents = $totals->grossLaborCents();
        $hours = (float) $laborLines->sum(function (RepairOrderLine $line): float {
            return (float) ($line->labor_billed_hours ?? $line->quantity);
        });

        $laborCostRate = (int) ($repairOrder->assignedTechnician?->labor_cost_cents ?? 0);
        $costCents = $laborCostRate > 0 ? (int) round($hours * $laborCostRate) : 0;
        $gpCents = $salesCents - $costCents;

        return [
            'sales_cents' => $salesCents,
            'cost_cents' => $costCents,
            'gp_cents' => $gpCents,
            'margin_percent' => $this->marginPercent($gpCents, $salesCents),
            'hours' => $hours,
        ];
    }

    /**
     * @param  Collection<int, RepairOrderLine>  $billableLines
     * @return array{
     *     label: string,
     *     tone: string|null,
     *     meter_percent: int|null,
     *     items: list<array{label: string, detail: string}>,
     *     footer: string|null
     * }
     */
    private function procurementSummary(Collection $billableLines, RepairOrder $repairOrder): array
    {
        $partLines = $billableLines
            ->filter(fn (RepairOrderLine $line): bool => $line->isPart() && $line->part_source !== PartLineSource::CustomerSupplied);

        $total = $partLines->count();

        if ($total === 0) {
            return [
                'label' => 'No shop parts',
                'tone' => null,
                'meter_percent' => null,
                'items' => [
                    ['label' => 'Shop parts', 'detail' => '0 lines'],
                ],
                'footer' => null,
            ];
        }

        $orderedStates = [
            PartProcurementState::Ordered,
            PartProcurementState::Partial,
            PartProcurementState::Received,
            PartProcurementState::Installed,
        ];

        $ordered = $partLines
            ->filter(fn (RepairOrderLine $line): bool => in_array($line->procurementState(), $orderedStates, true))
            ->count();

        $received = $partLines
            ->filter(fn (RepairOrderLine $line): bool => in_array($line->procurementState(), [PartProcurementState::Received, PartProcurementState::Installed], true))
            ->count();

        $needsOrder = $partLines
            ->filter(fn (RepairOrderLine $line): bool => in_array($line->procurementState(), [PartProcurementState::None, PartProcurementState::Sourcing], true))
            ->count();

        $meterPercent = (int) round(($ordered / $total) * 100);
        $items = [
            ['label' => 'Ordered or beyond', 'detail' => (string) $ordered],
            ['label' => 'Received / installed', 'detail' => (string) $received],
            ['label' => 'Needs ordered', 'detail' => (string) $needsOrder],
            ['label' => 'Shop part lines', 'detail' => (string) $total],
        ];

        $launcher = app(PartsCatalogLauncher::class);

        if ($launcher->configured()) {
            $items[] = ['label' => 'PO', 'detail' => $launcher->poNumber($repairOrder)];
        }

        return [
            'label' => 'Ordered '.$ordered.'/'.$total,
            'tone' => $needsOrder > 0 ? 'warn' : 'good',
            'meter_percent' => $meterPercent,
            'items' => $items,
            'footer' => $needsOrder > 0 ? $needsOrder.' line'.($needsOrder === 1 ? '' : 's').' still need ordering' : null,
        ];
    }

    /**
     * @param  Collection<int, RepairOrderLine>  $billableLines
     * @return array{count: int, label: string}
     */
    private function warrantyExposureSummary(Collection $billableLines): array
    {
        $lines = $billableLines
            ->filter(fn (RepairOrderLine $line): bool => $line->isPart()
                && ($line->part_warranty_impact ?? PartLineWarrantyImpact::None) !== PartLineWarrantyImpact::None);

        $count = $lines->count();

        return [
            'count' => $count,
            'label' => $count === 0 ? 'None flagged' : $count.' line'.($count === 1 ? '' : 's'),
        ];
    }

    /**
     * @return array{
     *     value: string,
     *     percent: int|null,
     *     tone: string|null,
     *     items: list<array{label: string, detail: string}>,
     *     footer: string|null
     * }
     */
    private function approvalSummary(RepairOrder $repairOrder): array
    {
        $scopes = $repairOrder->concerns
            ->filter(fn (RepairOrderConcern $concern): bool => $concern->disposition->showsInScopeHeader());

        $total = $scopes->count();

        if ($total === 0) {
            return [
                'value' => 'n/a',
                'percent' => null,
                'tone' => null,
                'items' => [
                    ['label' => 'Scopes', 'detail' => '0'],
                ],
                'footer' => null,
            ];
        }

        $approved = $scopes
            ->filter(fn (RepairOrderConcern $concern): bool => $concern->disposition === RepairOrderConcernDisposition::Approved)
            ->count();
        $recommended = $scopes
            ->filter(fn (RepairOrderConcern $concern): bool => $concern->disposition === RepairOrderConcernDisposition::Recommended)
            ->count();
        $deferred = $scopes
            ->filter(fn (RepairOrderConcern $concern): bool => $concern->disposition === RepairOrderConcernDisposition::Deferred)
            ->count();
        $declined = $scopes
            ->filter(fn (RepairOrderConcern $concern): bool => $concern->disposition === RepairOrderConcernDisposition::Declined)
            ->count();

        $percent = (int) round(($approved / $total) * 100);

        return [
            'value' => $percent.'%',
            'percent' => $percent,
            'tone' => $recommended > 0 ? 'warn' : ($percent >= 100 ? 'good' : null),
            'items' => [
                ['label' => 'Approved', 'detail' => (string) $approved],
                ['label' => 'Pending decision', 'detail' => (string) $recommended],
                ['label' => 'Deferred', 'detail' => (string) $deferred],
                ['label' => 'Declined', 'detail' => (string) $declined],
                ['label' => 'Scopes on estimate', 'detail' => (string) $total],
            ],
            'footer' => $recommended > 0
                ? $recommended.' scope'.($recommended === 1 ? '' : 's').' waiting on customer decision'
                : null,
        ];
    }

    /**
     * Customer-facing estimate breakdown — what builds the total.
     *
     * @return array{title: string, items: list<array{label: string, detail: string}>, footer: string|null}
     */
    private function customerTotalInspectItems(EstimateTotals $totals): array
    {
        $items = [];

        if ($totals->grossLaborCents() > 0) {
            $items[] = ['label' => 'Labor', 'detail' => $totals->format($totals->grossLaborCents())];
        }

        if ($totals->grossPartsCents() > 0) {
            $items[] = ['label' => 'Parts', 'detail' => $totals->format($totals->grossPartsCents())];
        }

        if ($totals->feesCents() > 0) {
            $items[] = ['label' => 'Fees', 'detail' => $totals->format($totals->feesCents())];
        }

        if ($totals->standingDiscountCents() > 0) {
            $items[] = ['label' => 'Discount', 'detail' => '−'.$totals->format($totals->standingDiscountCents())];
        }

        $items[] = ['label' => 'Subtotal', 'detail' => $totals->format($totals->subtotalBeforeTaxCents())];

        if ($totals->taxCents() > 0) {
            $items[] = ['label' => 'Tax', 'detail' => $totals->format($totals->taxCents())];
        }

        $items[] = ['label' => 'Customer total', 'detail' => $totals->format($totals->totalCents())];

        return [
            'title' => 'Estimate total',
            'items' => $items,
            'footer' => 'Customer-facing breakdown',
        ];
    }

    /**
     * Owner/advisor profitability — costs, GP, and shop health levers.
     *
     * @param  array{sales_cents: int, cost_cents: int, gp_cents: int, margin_percent: int|null}  $partsMetrics
     * @param  array{sales_cents: int, cost_cents: int, gp_cents: int, margin_percent: int|null, hours: float}  $laborMetrics
     * @param  array{label: string, tone: string}  $marginPosture
     * @param  array{count: int, label: string}  $warrantyExposure
     * @return array{title: string, items: list<array{label: string, detail: string}>, footer: string|null}
     */
    private function marginInspectItems(
        EstimateTotals $totals,
        array $partsMetrics,
        array $laborMetrics,
        int $serviceRevenueCents,
        int $grossProfitCents,
        ?int $marginPercent,
        ?int $markupPercent,
        array $marginPosture,
        array $warrantyExposure,
    ): array {
        $targets = ShopExcellenceTargets::current();
        $laborSoldCents = $totals->grossLaborCents();
        $partsSoldCents = $totals->grossPartsCents();
        $hours = $laborMetrics['hours'];
        $effectiveLaborRateCents = $hours > 0 ? (int) round($laborSoldCents / $hours) : null;
        $profitPerHourCents = $hours > 0 ? (int) round($grossProfitCents / $hours) : null;
        $partsMixPercent = $this->marginPercent($partsSoldCents, $serviceRevenueCents);
        $laborMixPercent = $this->marginPercent($laborSoldCents, $serviceRevenueCents);
        $elrFloor = $targets['effective_labor_rate_floor_cents'] ?? $targets['posted_labor_rate_cents'];

        $items = [
            ['label' => 'Parts cost', 'detail' => $totals->format($partsMetrics['cost_cents'])],
            [
                'label' => 'Labor cost',
                'detail' => $laborMetrics['cost_cents'] > 0
                    ? $totals->format($laborMetrics['cost_cents'])
                    : 'Assign technician cost',
            ],
            ['label' => 'Parts GP', 'detail' => $totals->format($partsMetrics['gp_cents'])],
            ['label' => 'Labor GP', 'detail' => $totals->format($laborMetrics['gp_cents'])],
            ['label' => 'Gross profit', 'detail' => $totals->format($grossProfitCents)],
        ];

        if ($marginPercent !== null) {
            $items[] = ['label' => 'Margin', 'detail' => $marginPercent.'%'];
        }

        if ($markupPercent !== null) {
            $items[] = ['label' => 'Markup', 'detail' => $markupPercent.'%'];
        }

        $items[] = ['label' => 'Target', 'detail' => $marginPosture['label']];

        if ($partsMetrics['margin_percent'] !== null) {
            $items[] = ['label' => 'Parts margin', 'detail' => $partsMetrics['margin_percent'].'%'];
        }

        if ($laborMetrics['margin_percent'] !== null) {
            $items[] = ['label' => 'Labor margin', 'detail' => $laborMetrics['margin_percent'].'%'];
        }

        if ($effectiveLaborRateCents !== null) {
            $items[] = ['label' => 'Effective labor rate', 'detail' => $totals->format($effectiveLaborRateCents).'/hr'];
        }

        if ($profitPerHourCents !== null) {
            $items[] = ['label' => 'Profit / hour', 'detail' => $totals->format($profitPerHourCents).'/hr'];
        }

        if ($partsMixPercent !== null) {
            $items[] = ['label' => 'Parts mix', 'detail' => $partsMixPercent.'%'];
        }

        if ($laborMixPercent !== null) {
            $items[] = ['label' => 'Labor mix', 'detail' => $laborMixPercent.'%'];
        }

        $items[] = ['label' => 'Warranty exposure', 'detail' => $warrantyExposure['label']];

        $footerParts = ['Parts target '.$targets['parts_margin_target_percent'].'%'];

        if ($elrFloor !== null && $effectiveLaborRateCents !== null) {
            $footerParts[] = $effectiveLaborRateCents >= $elrFloor
                ? 'ELR at or above floor'
                : 'ELR below floor '.$totals->format($elrFloor).'/hr';
        } elseif ($marginPercent !== null && $marginPercent < $targets['parts_margin_target_percent']) {
            $footerParts[] = 'Below target by '.($targets['parts_margin_target_percent'] - $marginPercent).'%';
        }

        return [
            'title' => 'Repair order profitability',
            'items' => $items,
            'footer' => implode(' · ', $footerParts),
        ];
    }

    /**
     * @return array{label: string, tone: string}
     */
    private function marginPosture(?int $marginPercent): array
    {
        $target = ShopExcellenceTargets::current()['parts_margin_target_percent'];

        if ($marginPercent === null) {
            return ['label' => 'Unknown', 'tone' => 'neutral'];
        }

        if ($marginPercent >= $target + 5) {
            return ['label' => 'Excellent', 'tone' => 'excellent'];
        }

        if ($marginPercent >= $target) {
            return ['label' => 'Healthy', 'tone' => 'healthy'];
        }

        if ($marginPercent >= max(25, $target - 12)) {
            return ['label' => 'Thin', 'tone' => 'thin'];
        }

        return ['label' => 'Low', 'tone' => 'low'];
    }

    private function marginPercent(int $gpCents, int $salesCents): ?int
    {
        if ($salesCents <= 0) {
            return null;
        }

        return (int) round(($gpCents / $salesCents) * 100);
    }

    private function markupPercent(int $gpCents, int $costCents): ?int
    {
        if ($costCents <= 0) {
            return null;
        }

        return (int) round(($gpCents / $costCents) * 100);
    }

    private function formatHours(float $hours): string
    {
        if ($hours <= 0) {
            return '0 hrs';
        }

        $formatted = rtrim(rtrim(number_format($hours, 1, '.', ''), '0'), '.');

        return $formatted.' hr'.($hours === 1.0 ? '' : 's');
    }
}
