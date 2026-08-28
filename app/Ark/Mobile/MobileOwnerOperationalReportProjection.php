<?php

namespace App\Ark\Mobile;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Reports\OperationalReportDateScope;
use App\Ark\Operations\Reports\OperationalReportPaymentReconciliation;
use App\Ark\Operations\Reports\OperationalReportRangeMetrics;
use App\Ark\Operations\Reports\OperationalReportTab;
use App\Ark\Operations\Reports\OperationalReportTotals;
use Brick\Money\Money;
use Illuminate\Support\Collection;

final class MobileOwnerOperationalReportProjection
{
    /**
     * Read-only operational report pulse for mobile — same metrics authority as
     * desktop `/app/reports/operational`. Posted sales truth; no parallel metrics store.
     *
     * @return array<string, mixed>
     */
    public function forRange(?string $fromDate, ?string $toDate): array
    {
        [$from, $to] = OperationalReportDateScope::resolveRange($fromDate, $toDate);
        $metrics = new OperationalReportRangeMetrics($from, $to);
        $reconciliation = (new OperationalReportPaymentReconciliation($from, $to))->summary();

        $shopTz = OperationalReportDateScope::displayTimezone();

        return [
            'range_label' => OperationalReportDateScope::shopRangeLabel($from, $to),
            'from_date' => $from->copy()->timezone($shopTz)->toDateString(),
            'to_date' => $to->copy()->timezone($shopTz)->toDateString(),
            'min_date' => OperationalReportDateScope::shopDateString(
                OperationalReportDateScope::trustworthyDataStartsAt(),
            ),
            'source_label' => 'Posted sales truth · workflow metrics use open queue',
            'tabs' => $this->tabs(),
            'pulse' => [
                'kpis' => $this->kpis($metrics->kpis()),
            ],
            'margin' => [
                'rows' => $this->marginRows($metrics->marginHealthRows()),
            ],
            'financial' => [
                'mix_rows' => $metrics->financialRows(),
                'reconciliation' => $this->reconciliation($reconciliation),
            ],
            'owner_pl' => $this->ownerPl($metrics->ownerPlSummary()),
            'production' => $this->production($metrics),
            'poll_after_seconds' => 300,
        ];
    }

    /**
     * @return list<array{key: string, label: string, description: string}>
     */
    private function tabs(): array
    {
        return [
            [
                'key' => 'pulse',
                'label' => 'Daily KPIs',
                'description' => OperationalReportTab::Operations->description(),
            ],
            [
                'key' => 'margin',
                'label' => 'Margin health',
                'description' => OperationalReportTab::MarginHealth->description(),
            ],
            [
                'key' => 'financial',
                'label' => 'Financial',
                'description' => OperationalReportTab::Financial->description(),
            ],
            [
                'key' => 'owner_pl',
                'label' => 'Owner P&L',
                'description' => OperationalReportTab::OwnerPl->description(),
            ],
            [
                'key' => 'production',
                'label' => 'Production',
                'description' => OperationalReportTab::Production->description(),
            ],
        ];
    }

    /**
     * @param  list<array{label: string, value: string, hint: string, tone: 'good'|'warn'|null}>  $rows
     * @return list<array{label: string, value: string, hint: string|null, tone: string|null}>
     */
    private function kpis(array $rows): array
    {
        return collect($rows)
            ->map(fn (array $row): array => [
                'label' => (string) ($row['label'] ?? ''),
                'value' => (string) ($row['value'] ?? ''),
                'hint' => filled($row['hint'] ?? null) ? (string) $row['hint'] : null,
                'tone' => filled($row['tone'] ?? null) ? (string) $row['tone'] : null,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<array{metric: string, actual: string, target: string, posture: string, tone: 'good'|'warn'|null, action: string}>  $rows
     * @return list<array{metric: string, actual: string, target: string, posture: string, action: string, tone: string|null}>
     */
    private function marginRows(array $rows): array
    {
        return collect($rows)
            ->map(fn (array $row): array => [
                'metric' => (string) ($row['metric'] ?? ''),
                'actual' => (string) ($row['actual'] ?? ''),
                'target' => (string) ($row['target'] ?? ''),
                'posture' => (string) ($row['posture'] ?? ''),
                'action' => (string) ($row['action'] ?? ''),
                'tone' => filled($row['tone'] ?? null) ? (string) $row['tone'] : null,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $summary
     * @return array<string, mixed>
     */
    private function reconciliation(array $summary): array
    {
        $posted = $summary['posted_ro_summary'] ?? [];

        return [
            'reconciles' => (bool) ($summary['reconciles'] ?? false),
            'delta_label' => (string) ($summary['delta_label'] ?? ''),
            'posted_ro_summary' => [
                'service' => (string) ($posted['service'] ?? ''),
                'tax' => (string) ($posted['tax'] ?? ''),
                'total' => (string) ($posted['total'] ?? ''),
            ],
            'rows' => collect($summary['rows'] ?? [])
                ->map(fn (array $row): array => [
                    'label' => (string) ($row['label'] ?? ''),
                    'amount' => (string) ($row['amount'] ?? ''),
                    'note' => (string) ($row['note'] ?? ''),
                    'emphasis' => (bool) ($row['emphasis'] ?? false),
                    'tone' => filled($row['tone'] ?? null) ? (string) $row['tone'] : null,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $summary
     * @return array<string, mixed>
     */
    private function ownerPl(array $summary): array
    {
        $benchmark = $summary['benchmark'] ?? null;

        return [
            'range_days' => (int) ($summary['range_days'] ?? 0),
            'posted_count' => (int) ($summary['posted_count'] ?? 0),
            'configured_fixed_costs' => (bool) ($summary['configured_fixed_costs'] ?? false),
            'disclaimer' => (string) ($summary['disclaimer'] ?? ''),
            'pl_lines' => collect($summary['pl_lines'] ?? [])
                ->map(fn (array $line): array => [
                    'label' => (string) ($line['label'] ?? ''),
                    'amount' => (string) ($line['amount'] ?? ''),
                    'percent' => filled($line['percent'] ?? null) ? (string) $line['percent'] : null,
                    'indent' => (bool) ($line['indent'] ?? false),
                    'emphasis' => (string) ($line['emphasis'] ?? 'normal'),
                    'tone' => filled($line['tone'] ?? null) ? (string) $line['tone'] : null,
                    'note' => filled($line['note'] ?? null) ? (string) $line['note'] : null,
                ])
                ->values()
                ->all(),
            'tax_lines' => collect($summary['tax_lines'] ?? [])
                ->map(fn (array $line): array => [
                    'label' => (string) ($line['label'] ?? ''),
                    'amount' => (string) ($line['amount'] ?? ''),
                    'source' => (string) ($line['source'] ?? ''),
                    'note' => filled($line['note'] ?? null) ? (string) $line['note'] : null,
                ])
                ->values()
                ->all(),
            'benchmark' => is_array($benchmark) ? [
                'net_target_percent' => (int) ($benchmark['net_target_percent'] ?? 0),
                'net_target_label' => (string) ($benchmark['net_target_label'] ?? ''),
                'estimated_net_label' => filled($benchmark['estimated_net_label'] ?? null)
                    ? (string) $benchmark['estimated_net_label']
                    : null,
                'gap_label' => filled($benchmark['gap_label'] ?? null) ? (string) $benchmark['gap_label'] : null,
                'tone' => filled($benchmark['tone'] ?? null) ? (string) $benchmark['tone'] : null,
                'posture' => (string) ($benchmark['posture'] ?? ''),
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function production(OperationalReportRangeMetrics $metrics): array
    {
        $activeRepairOrders = RepairOrder::query()
            ->with([
                'assignedTechnician:id,name',
                'concerns:id,repair_order_id,disposition',
                'lines:id,repair_order_id,repair_order_concern_id,type,quantity,unit_price_cents,part_cost_cents,subtotal_cents,tax_cents,shop_fee_cents,total_cents,procurement_state',
            ])
            ->whereIn('status', RepairOrderStatus::operationalQueueValues())
            ->tap(fn ($query) => OperationalReportDateScope::applyTrustworthyDataFloor($query))
            ->get();

        $activeTotalsByRepairOrder = OperationalReportTotals::totalCentsByRepairOrderId($activeRepairOrders->pluck('id'));
        $activeLaborCentsByRepairOrder = OperationalReportTotals::laborCentsByRepairOrderId($activeRepairOrders->pluck('id'));

        return [
            'pressure' => $this->pressureRows($activeRepairOrders, $activeTotalsByRepairOrder),
            'advisors' => $metrics->advisorRows(),
            'technicians' => $metrics->technicianRows($activeRepairOrders, $activeLaborCentsByRepairOrder),
        ];
    }

    /**
     * @param  Collection<int, RepairOrder>  $activeRepairOrders
     * @param  Collection<int, int>  $totalsByRepairOrder
     * @return list<array{pressure: string, count: int, value: string, action: string}>
     */
    private function pressureRows(Collection $activeRepairOrders, Collection $totalsByRepairOrder): array
    {
        $waitingApproval = $activeRepairOrders->where('status', RepairOrderStatus::WaitingApproval);
        $waitingParts = $activeRepairOrders->filter(
            fn (RepairOrder $repairOrder): bool => $repairOrder->workboardLaneStatus()->is(RepairOrderStatus::WaitingParts),
        );
        $unpaidPickups = $activeRepairOrders->filter(
            fn (RepairOrder $repairOrder): bool => $repairOrder->status->is(RepairOrderStatus::ReadyPickup) && ! $repairOrder->isPaid(),
        );
        $readyExecution = $activeRepairOrders
            ->whereIn('status', [RepairOrderStatus::Approved, RepairOrderStatus::ReadyForWork])
            ->reject(fn (RepairOrder $repairOrder): bool => $repairOrder->hasUnresolvedApprovedParts());
        $stalled = $activeRepairOrders->filter(
            fn (RepairOrder $repairOrder): bool => $repairOrder->updated_at->lt(now()->subHours(4)),
        );

        return [
            [
                'pressure' => 'Approvals aging',
                'count' => $waitingApproval->count(),
                'value' => $this->money(OperationalReportTotals::sumTotalCentsFor($waitingApproval, $totalsByRepairOrder)),
                'action' => 'Follow up customer authorization',
            ],
            [
                'pressure' => 'Parts backlog',
                'count' => $waitingParts->count(),
                'value' => $waitingParts->map(fn (RepairOrder $repairOrder): ?string => $repairOrder->partsBlockerSummary())->filter()->join(' · ') ?: 'No blocker detail',
                'action' => 'Clear procurement blockers',
            ],
            [
                'pressure' => 'Ready execution',
                'count' => $readyExecution->count(),
                'value' => $readyExecution->whereNull('assigned_technician_id')->count().' unassigned',
                'action' => 'Assign and dispatch work',
            ],
            [
                'pressure' => 'Unpaid pickup',
                'count' => $unpaidPickups->count(),
                'value' => $this->money(OperationalReportTotals::sumTotalCentsFor($unpaidPickups, $totalsByRepairOrder)),
                'action' => 'Collect before release',
            ],
            [
                'pressure' => 'Stalled ROs',
                'count' => $stalled->count(),
                'value' => 'No movement in 4h',
                'action' => 'Review owner and next step',
            ],
        ];
    }

    private function money(int $cents): string
    {
        return '$'.Money::ofMinor($cents, 'USD')->getAmount()->toScale(2)->__toString();
    }
}
