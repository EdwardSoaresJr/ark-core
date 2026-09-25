<?php

namespace App\Ark\Operations\Scoreboard;

use App\Ark\Operations\Communications\CommunicationEvent;
use App\Ark\Operations\Communications\OperationalCommunicationType;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderLostReason;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Reports\OperationalReportDateScope;
use App\Ark\Operations\Reports\OperationalReportTotals;
use App\Ark\Operations\Reports\Standards\ReportingStandardsV1;
use App\Ark\Operations\ShopExcellence\ShopExcellenceTargets;
use App\Models\User;
use Brick\Money\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class ShopOperatingScoreboard
{
    /** @var list<int> */
    private const AGING_DAYS = [2, 5, 10, 30];

    /** @var list<OperationalCommunicationType> */
    private const CONTACT_TYPES = [
        OperationalCommunicationType::EstimateSent,
        OperationalCommunicationType::EstimateViewed,
        OperationalCommunicationType::ApprovalFollowUp,
        OperationalCommunicationType::CustomerReply,
        OperationalCommunicationType::AdvisorNote,
    ];

    public function __construct(
        private readonly EstimateTotalsCalculator $calculator,
    ) {}

    /**
     * @param  array{
     *     key: string,
     *     label: string,
     *     from: Carbon,
     *     to: Carbon,
     *     previous_from: Carbon|null,
     *     previous_to: Carbon|null,
     *     previous_label: string|null
     * }  $period
     * @return array<string, mixed>
     */
    public function snapshot(array $period): array
    {
        $targets = ShopExcellenceTargets::current();
        $current = $this->measure($period['from'], $period['to']);
        $previous = $period['previous_from'] !== null && $period['previous_to'] !== null
            ? $this->measure($period['previous_from'], $period['previous_to'])
            : null;
        $shop = $this->shopNow();

        return [
            'generated_at' => OperationalReportDateScope::shopNow()->format('M j, Y g:i A'),
            'period' => [
                'key' => $period['key'],
                'label' => $period['label'],
                'previous_label' => $period['previous_label'],
            ],
            'periods' => ShopOperatingScoreboardPeriod::options(),
            'explanation' => PresentedConcernPopulation::explanation(),
            'metrics' => $current,
            'previous' => $previous,
            'kpis' => $this->kpis($current, $previous, $targets, $period['previous_label']),
            'money' => $this->moneyFlow($current),
            'now' => $shop['now'],
            'queues' => $shop['queues'],
            'cycle' => $this->cyclePanel($current, $shop['aging']),
            'sales' => $this->salesPanel($current),
            'production' => $this->productionPanel($current, $period['from'], $period['to']),
            'parts' => $this->partsPanel($current, $period['from'], $period['to'], $targets),
        ];
    }

    /**
     * @param  array{from: Carbon, to: Carbon}  $period
     * @return array{title: string, note: string|null, rows: list<array{primary: string, secondary: string, meta: string, url: string|null}>}
     */
    public function drilldown(array $period, string $focus, ?int $agingDays, bool $linkRepairOrders): array
    {
        return match ($focus) {
            'opened' => $this->openedRows($period['from'], $period['to'], $linkRepairOrders),
            'close' => $this->closeRows($period['from'], $period['to'], $linkRepairOrders),
            'hours' => $this->hourRows($period['from'], $period['to'], $linkRepairOrders),
            'cycle' => $this->cycleRows($period['from'], $period['to'], $linkRepairOrders),
            'authorized' => $this->backlogRows(true, $linkRepairOrders),
            'not_authorized' => $this->backlogRows(false, $linkRepairOrders),
            'aging' => $this->agingRows($agingDays ?? 10, $linkRepairOrders),
            'parts' => $this->partRows($period['from'], $period['to'], $linkRepairOrders),
            'presentation' => $this->queueRows('presentation', $linkRepairOrders),
            'follow_up' => $this->queueRows('follow_up', $linkRepairOrders),
            'aging_authorized' => $this->queueRows('aging_authorized', $linkRepairOrders),
            'pickup' => $this->queueRows('pickup', $linkRepairOrders),
            'stalled' => $this->queueRows('stalled', $linkRepairOrders),
            'lost' => $this->lostRows($period['from'], $period['to'], $linkRepairOrders),
            default => ['title' => 'Repair orders', 'note' => null, 'rows' => []],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function measure(Carbon $from, Carbon $to): array
    {
        $openDays = OperationalReportDateScope::shopOpenDayCount($from, $to);
        $opened = OperationalReportDateScope::openedBetween(
            RepairOrder::query()->with(['lines.concern']),
            $from,
            $to,
        )->get();

        $approvedCents = 0;
        $presentedCents = 0;
        $recommendedCents = 0;
        $draftCents = 0;
        $declinedCents = 0;
        $deferredCents = 0;
        $approvedRoCount = 0;
        $presentedRoCount = 0;

        foreach ($opened as $repairOrder) {
            $money = $this->moneyFor($repairOrder);
            $presented = $money['approved'] + $money['recommended'] + $money['declined'] + $money['deferred'];
            $approvedCents += $money['approved'];
            $presentedCents += $presented;
            $recommendedCents += $money['recommended'];
            $draftCents += $money['draft'];
            $declinedCents += $money['declined'];
            $deferredCents += $money['deferred'];
            if ($money['approved'] > 0) {
                $approvedRoCount++;
            }
            if ($presented > 0) {
                $presentedRoCount++;
            }
        }

        $components = OperationalReportTotals::postedSalesComponents($from, $to);
        $invoice = OperationalReportTotals::postedInvoiceFigures($from, $to);
        $soldHours = $this->soldHours($from, $to);
        $cycleDays = $this->cycleDays($from, $to);
        $partsComplete = $components['parts_sales_missing_cost_cents'] === 0;
        $partsMargin = $partsComplete
            ? ReportingStandardsV1::grossMarginPercent($components['parts_cents'], $components['parts_cents'] - $components['parts_gp_cents'])
            : null;
        $laborRateComplete = ! ($components['labor_cents'] > 0 && $soldHours <= 0);
        $lost = $this->lostRepairOrders($from, $to);
        $lostRecommended = 0;
        $noResponseCount = 0;
        $noResponseCents = 0;

        foreach ($lost as $repairOrder) {
            $recommended = $this->moneyFor($repairOrder)['recommended'];
            $lostRecommended += $recommended;
            if ($repairOrder->lost_reason_key === RepairOrderLostReason::NoResponse) {
                $noResponseCount++;
                $noResponseCents += $recommended;
            }
        }

        return [
            'open_days' => $openDays,
            'opened_count' => $opened->count(),
            'ros_per_open_day' => ShopOperatingScoreboardMath::perOpenDay($opened->count(), $openDays),
            'approved_cents' => $approvedCents,
            'presented_cents' => $presentedCents,
            'recommended_cents' => $recommendedCents,
            'draft_cents' => $draftCents,
            'declined_cents' => $declinedCents,
            'deferred_cents' => $deferredCents,
            'dollar_close_percent' => ReportingStandardsV1::approvalRatePercent($approvedCents, $presentedCents),
            'ro_close_percent' => ReportingStandardsV1::approvedRepairOrderRatePercent($approvedRoCount, $presentedRoCount),
            'presented_ro_count' => $presentedRoCount,
            'approved_ro_count' => $approvedRoCount,
            'sold_hours' => round($soldHours, 2),
            'sold_hours_per_open_day' => ShopOperatingScoreboardMath::perOpenDay($soldHours, $openDays),
            'cycle_days' => $cycleDays,
            'median_cycle_days' => ShopOperatingScoreboardMath::median($cycleDays),
            'average_cycle_days' => ShopOperatingScoreboardMath::average($cycleDays),
            'posted_count' => count($cycleDays),
            'posted_per_open_day' => ShopOperatingScoreboardMath::perOpenDay(count($cycleDays), $openDays),
            'posted_sales_cents' => $invoice['sales_cents'],
            'sales_per_open_day_cents' => $openDays > 0 ? (int) round($invoice['sales_cents'] / $openDays) : null,
            'labor_cents' => $components['labor_cents'],
            'parts_cents' => $components['parts_cents'],
            'effective_labor_rate_cents' => $laborRateComplete && $soldHours > 0 ? (int) round($components['labor_cents'] / $soldHours) : null,
            'labor_rate_complete' => $laborRateComplete,
            'parts_sales_cents' => $components['parts_cents'],
            'parts_gp_cents' => $components['parts_gp_cents'],
            'parts_missing_cost_cents' => $components['parts_sales_missing_cost_cents'],
            'parts_margin_percent' => $partsMargin,
            'parts_margin_complete' => $partsComplete,
            'lost_count' => $lost->count(),
            'lost_recommended_cents' => $lostRecommended,
            'no_response_count' => $noResponseCount,
            'no_response_cents' => $noResponseCents,
        ];
    }

    /**
     * @return array{now: array<string, mixed>, queues: list<array<string, mixed>>, aging: array<string, int>}
     */
    private function shopNow(): array
    {
        $open = $this->openRepairOrders();
        $contacts = $this->contactsFor($open->pluck('id'));
        $targets = ShopExcellenceTargets::current();
        $agingThreshold = $targets['median_cycle_target_days'] ?? 5.0;
        $stall = $this->stallRule($targets);
        $authorizedCents = 0;
        $authorizedCount = 0;
        $notAuthorizedCents = 0;
        $notAuthorizedCount = 0;
        $aging = array_fill_keys(array_map('strval', self::AGING_DAYS), 0);
        $statusCounts = [
            'production' => 0,
            'ready' => 0,
            'waiting_customer' => 0,
            'waiting_parts' => 0,
            'quality' => 0,
        ];
        $queues = [
            'presentation' => 0,
            'follow_up' => 0,
            'aging_authorized' => 0,
            'pickup_due' => 0,
            'pickup' => 0,
            'stalled' => 0,
        ];
        $pickupDueCents = 0;

        foreach ($open as $repairOrder) {
            $money = $this->moneyFor($repairOrder);
            $notAuthorized = $money['draft'] + $money['recommended'] + $money['deferred'];
            if ($money['approved'] > 0) {
                $authorizedCents += $money['approved'];
                $authorizedCount++;
            }
            if ($notAuthorized > 0) {
                $notAuthorizedCents += $notAuthorized;
                $notAuthorizedCount++;
            }

            $age = $this->ageDays($repairOrder);
            foreach (self::AGING_DAYS as $days) {
                if ($age > $days) {
                    $aging[(string) $days]++;
                }
            }

            $status = $repairOrder->status->enum();
            if ($status === RepairOrderStatus::InProgress) {
                $statusCounts['production']++;
            }
            if (in_array($status, [RepairOrderStatus::ReadyPickup, RepairOrderStatus::Completed, RepairOrderStatus::Invoiced], true)) {
                $statusCounts['ready']++;
                $queues['pickup']++;
                $balance = $repairOrder->balanceDue();
                if ($balance->hasIssuedInvoice && $balance->balanceDueCents > 0) {
                    $queues['pickup_due']++;
                    $pickupDueCents += $balance->balanceDueCents;
                }
            }
            if ($status === RepairOrderStatus::WaitingApproval) {
                $statusCounts['waiting_customer']++;
            }
            if ($status === RepairOrderStatus::WaitingParts) {
                $statusCounts['waiting_parts']++;
            }
            if ($status === RepairOrderStatus::QualityCheck) {
                $statusCounts['quality']++;
            }

            $sent = isset($contacts['sent'][$repairOrder->id]);
            if (
                in_array($status, [RepairOrderStatus::Draft, RepairOrderStatus::Estimate], true)
                && ($money['draft'] > 0 || $money['recommended'] > 0)
                && ! $sent
            ) {
                $queues['presentation']++;
            }
            if ($money['recommended'] > 0 || $status === RepairOrderStatus::WaitingApproval) {
                $queues['follow_up']++;
            }
            if (
                in_array($status, [RepairOrderStatus::Approved, RepairOrderStatus::ReadyForWork, RepairOrderStatus::InProgress, RepairOrderStatus::WaitingParts], true)
                && $age > $agingThreshold
            ) {
                $queues['aging_authorized']++;
            }
            if ($stall !== null && $age >= $stall['days'] && ! $this->isActiveProduction($status)) {
                $queues['stalled']++;
            }
        }

        $agingHint = $targets['median_cycle_target_days'] !== null
            ? 'Authorized work open longer than the '.number_format((float) $targets['median_cycle_target_days'], 1).' day cycle target.'
            : 'No cycle target is set. Showing authorized work open more than 5 days.';

        return [
            'aging' => $aging,
            'now' => [
                'authorized_cents' => $authorizedCents,
                'authorized_label' => $this->money($authorizedCents),
                'authorized_count' => $authorizedCount,
                'not_authorized_cents' => $notAuthorizedCents,
                'not_authorized_label' => $this->money($notAuthorizedCents),
                'not_authorized_count' => $notAuthorizedCount,
                'open_count' => $open->count(),
                'counts' => [
                    ['label' => 'In production', 'count' => $statusCounts['production']],
                    ['label' => 'Waiting on customer', 'count' => $statusCounts['waiting_customer']],
                    ['label' => 'Waiting on parts', 'count' => $statusCounts['waiting_parts']],
                    ['label' => 'Quality check', 'count' => $statusCounts['quality']],
                    ['label' => 'Ready for pickup', 'count' => $statusCounts['ready']],
                ],
                'pickup_due_label' => $this->money($pickupDueCents),
                'pickup_due_count' => $queues['pickup_due'],
            ],
            'queues' => [
                [
                    'key' => 'presentation',
                    'label' => 'Presentation / decision needed',
                    'count' => $queues['presentation'],
                    'hint' => 'Draft or estimate, with dollars, and no estimate link on file. That does not prove it was never discussed in person.',
                    'focus' => 'presentation',
                ],
                [
                    'key' => 'follow_up',
                    'label' => 'Follow-up',
                    'count' => $queues['follow_up'],
                    'hint' => 'Recommended work or waiting approval. Last contact is shown only when an estimate event is on the repair order.',
                    'focus' => 'follow_up',
                ],
                [
                    'key' => 'aging_authorized',
                    'label' => 'Aging authorized work',
                    'count' => $queues['aging_authorized'],
                    'hint' => $agingHint,
                    'focus' => 'aging_authorized',
                ],
                [
                    'key' => 'pickup',
                    'label' => 'Ready for pickup',
                    'count' => $queues['pickup'],
                    'hint' => $queues['pickup_due'].' with a balance due, '.$this->money($pickupDueCents).'.',
                    'focus' => 'pickup',
                ],
                [
                    'key' => 'stalled',
                    'label' => 'Stalled',
                    'count' => $stall === null ? null : $queues['stalled'],
                    'hint' => $stall === null
                        ? 'No stall age is set. Set a stall age, or a median cycle target, before a repair order can be marked stalled.'
                        : $stall['hint'],
                    'focus' => 'stalled',
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>|null  $previous
     * @param  array<string, mixed>  $targets
     * @return list<array<string, mixed>>
     */
    private function kpis(array $current, ?array $previous, array $targets, ?string $previousLabel): array
    {
        $prior = $previousLabel !== null ? 'vs '.$previousLabel : null;

        return [
            $this->kpi(
                'ros_per_open_day',
                'ROs / open day',
                $current['ros_per_open_day'] !== null ? number_format($current['ros_per_open_day'], 2) : 'n/a',
                $current['opened_count'].' opened / '.$current['open_days'].' open days',
                $targets['opportunity_ros_per_open_day'],
                ShopOperatingScoreboardMath::toneForMinimum($current['ros_per_open_day'], $targets['opportunity_ros_per_open_day']),
                $this->trend($current['ros_per_open_day'], $previous['ros_per_open_day'] ?? null),
                $prior,
                'opened',
                false,
            ),
            $this->kpi(
                'dollar_close',
                'Dollar close',
                $current['dollar_close_percent'] !== null ? number_format($current['dollar_close_percent'], 1).'%' : 'n/a',
                $this->money($current['approved_cents']).' approved / '.$this->money($current['presented_cents']).' presented',
                $targets['dollar_close_target_percent'],
                ShopOperatingScoreboardMath::toneForMinimum($current['dollar_close_percent'], $targets['dollar_close_target_percent']),
                $this->trend($current['dollar_close_percent'], $previous['dollar_close_percent'] ?? null, 1),
                $prior,
                'close',
                true,
                '%',
            ),
            $this->kpi(
                'sold_hours',
                'Sold hours / open day',
                $current['sold_hours_per_open_day'] !== null ? number_format($current['sold_hours_per_open_day'], 2) : 'n/a',
                number_format($current['sold_hours'], 2).' billed hours on posted work',
                $targets['sold_labor_hours_per_open_day'],
                ShopOperatingScoreboardMath::toneForMinimum($current['sold_hours_per_open_day'], $targets['sold_labor_hours_per_open_day']),
                $this->trend($current['sold_hours_per_open_day'], $previous['sold_hours_per_open_day'] ?? null),
                $prior,
                'hours',
                true,
            ),
            $this->kpi(
                'cycle',
                'Median cycle',
                $current['median_cycle_days'] !== null ? number_format($current['median_cycle_days'], 1).' days' : 'n/a',
                'Average '.($current['average_cycle_days'] !== null ? number_format($current['average_cycle_days'], 1).' days' : 'n/a').' · opened to posted',
                $targets['median_cycle_target_days'],
                ShopOperatingScoreboardMath::toneForMaximum($current['median_cycle_days'], $targets['median_cycle_target_days']),
                $this->trend($current['median_cycle_days'], $previous['median_cycle_days'] ?? null, 1),
                $prior,
                'cycle',
                false,
                ' days',
            ),
            $this->kpi(
                'parts_margin',
                'Parts margin',
                $current['parts_margin_complete']
                    ? ($current['parts_margin_percent'] !== null ? $current['parts_margin_percent'].'%' : 'n/a')
                    : 'Incomplete',
                $current['parts_margin_complete']
                    ? $this->money($current['parts_gp_cents']).' gross profit'
                    : $this->money($current['parts_missing_cost_cents']).' parts sales have no cost',
                $targets['parts_margin_target_active'] ? (float) $targets['parts_margin_target_percent'] : null,
                $targets['parts_margin_target_active']
                    ? ShopOperatingScoreboardMath::toneForMinimum(
                        $current['parts_margin_percent'] !== null ? (float) $current['parts_margin_percent'] : null,
                        (float) $targets['parts_margin_target_percent'],
                    )
                    : null,
                $this->trend(
                    $current['parts_margin_percent'] !== null ? (float) $current['parts_margin_percent'] : null,
                    isset($previous['parts_margin_percent']) ? ($previous['parts_margin_percent'] !== null ? (float) $previous['parts_margin_percent'] : null) : null,
                    0,
                ),
                $prior,
                'parts',
                true,
                '%',
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $current
     * @return array<string, mixed>
     */
    private function moneyFlow(array $current): array
    {
        $presented = (int) $current['presented_cents'];
        $share = function (int $cents) use ($presented): float {
            if ($presented < 1) {
                return 0;
            }

            return round(($cents / $presented) * 100, 1);
        };

        return [
            'presented_label' => $this->money($presented),
            'segments' => [
                ['key' => 'approved', 'label' => 'Authorized', 'cents' => $current['approved_cents'], 'label_money' => $this->money($current['approved_cents']), 'share' => $share($current['approved_cents'])],
                ['key' => 'recommended', 'label' => 'Awaiting decision', 'cents' => $current['recommended_cents'], 'label_money' => $this->money($current['recommended_cents']), 'share' => $share($current['recommended_cents'])],
                ['key' => 'deferred', 'label' => 'Deferred', 'cents' => $current['deferred_cents'], 'label_money' => $this->money($current['deferred_cents']), 'share' => $share($current['deferred_cents'])],
                ['key' => 'declined', 'label' => 'Declined', 'cents' => $current['declined_cents'], 'label_money' => $this->money($current['declined_cents']), 'share' => $share($current['declined_cents'])],
            ],
            'draft_label' => $this->money($current['draft_cents']),
            'waiting_label' => $this->money($current['recommended_cents']),
        ];
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  array<string, int>  $aging
     * @return array<string, mixed>
     */
    private function cyclePanel(array $current, array $aging): array
    {
        return [
            'median' => $current['median_cycle_days'] !== null ? number_format($current['median_cycle_days'], 1).' days' : 'n/a',
            'average' => $current['average_cycle_days'] !== null ? number_format($current['average_cycle_days'], 1).' days' : 'n/a',
            'aging' => [
                ['days' => 2, 'count' => $aging['2'], 'focus' => 'aging'],
                ['days' => 5, 'count' => $aging['5'], 'focus' => 'aging'],
                ['days' => 10, 'count' => $aging['10'], 'focus' => 'aging'],
                ['days' => 30, 'count' => $aging['30'], 'focus' => 'aging'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $current
     * @return array<string, mixed>
     */
    private function salesPanel(array $current): array
    {
        return [
            ['label' => 'Presented', 'value' => $this->money($current['presented_cents'])],
            ['label' => 'Authorized', 'value' => $this->money($current['approved_cents'])],
            ['label' => 'Dollar close', 'value' => $current['dollar_close_percent'] !== null ? number_format($current['dollar_close_percent'], 1).'%' : 'n/a'],
            ['label' => 'RO close', 'value' => $current['ro_close_percent'] !== null ? number_format($current['ro_close_percent'], 1).'%' : 'n/a'],
            ['label' => 'Awaiting decision', 'value' => $this->money($current['recommended_cents'])],
            ['label' => 'Lost recommended', 'value' => $this->money($current['lost_recommended_cents'])],
            ['label' => 'No response', 'value' => $current['no_response_count'].' · '.$this->money($current['no_response_cents'])],
            ['label' => 'Posted sales / open day', 'value' => $current['sales_per_open_day_cents'] !== null ? $this->money($current['sales_per_open_day_cents']) : 'n/a'],
        ];
    }

    /**
     * @param  array<string, mixed>  $current
     * @return array<string, mixed>
     */
    private function productionPanel(array $current, Carbon $from, Carbon $to): array
    {
        return [
            'lines' => [
                ['label' => 'Sold hours', 'value' => number_format($current['sold_hours'], 2)],
                ['label' => 'Sold hours / open day', 'value' => $current['sold_hours_per_open_day'] !== null ? number_format($current['sold_hours_per_open_day'], 2) : 'n/a'],
                ['label' => 'Posted ROs', 'value' => (string) $current['posted_count']],
                ['label' => 'Posted ROs / open day', 'value' => $current['posted_per_open_day'] !== null ? number_format($current['posted_per_open_day'], 2) : 'n/a'],
                ['label' => 'Effective labor rate', 'value' => $current['labor_rate_complete']
                    ? ($current['effective_labor_rate_cents'] !== null ? $this->money($current['effective_labor_rate_cents']).'/hr' : 'n/a')
                    : 'Incomplete'],
                ['label' => 'Labor sales', 'value' => $this->money($current['labor_cents'])],
                ['label' => 'Parts sales', 'value' => $this->money($current['parts_cents'])],
            ],
            'technicians' => $this->technicianHours($from, $to),
        ];
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $targets
     * @return array<string, mixed>
     */
    private function partsPanel(array $current, Carbon $from, Carbon $to, array $targets): array
    {
        $buckets = [
            ['label' => 'Under 20%', 'count' => 0, 'sales_cents' => 0],
            ['label' => '20-30%', 'count' => 0, 'sales_cents' => 0],
            ['label' => '30-40%', 'count' => 0, 'sales_cents' => 0],
            ['label' => '40-50%', 'count' => 0, 'sales_cents' => 0],
            ['label' => '50%+', 'count' => 0, 'sales_cents' => 0],
        ];

        $lines = OperationalReportTotals::postedApprovedLineQuery($from, $to)
            ->where('repair_order_lines.type', RepairOrderLineType::Part->value)
            ->whereNotNull('repair_order_lines.part_cost_cents')
            ->where('repair_order_lines.subtotal_cents', '>', 0)
            ->get([
                'repair_order_lines.subtotal_cents',
                'repair_order_lines.part_cost_cents',
                'repair_order_lines.quantity',
            ]);

        foreach ($lines as $line) {
            $sale = (int) $line->subtotal_cents;
            $cost = (int) round(((float) $line->quantity) * (int) $line->part_cost_cents);
            $margin = (($sale - $cost) / $sale) * 100;
            $index = match (true) {
                $margin < 20 => 0,
                $margin < 30 => 1,
                $margin < 40 => 2,
                $margin < 50 => 3,
                default => 4,
            };
            $buckets[$index]['count']++;
            $buckets[$index]['sales_cents'] += $sale;
        }

        $knownSales = $current['parts_sales_cents'] - $current['parts_missing_cost_cents'];
        $costCents = $current['parts_margin_complete']
            ? $current['parts_sales_cents'] - $current['parts_gp_cents']
            : null;

        return [
            'sales_label' => $this->money($current['parts_sales_cents']),
            'cost_label' => $costCents !== null ? $this->money($costCents) : 'Incomplete',
            'gp_label' => $current['parts_margin_complete'] ? $this->money($current['parts_gp_cents']) : 'Incomplete',
            'margin_label' => $current['parts_margin_complete']
                ? ($current['parts_margin_percent'] !== null ? $current['parts_margin_percent'].'%' : 'n/a')
                : 'Incomplete',
            'target_active' => (bool) $targets['parts_margin_target_active'],
            'known_sales_label' => $this->money($knownSales),
            'buckets' => array_map(fn (array $bucket): array => [
                'label' => $bucket['label'],
                'count' => $bucket['count'],
                'sales_label' => $this->money($bucket['sales_cents']),
            ], $buckets),
        ];
    }

    /**
     * @return list<array{name: string, hours: string, labor: string}>
     */
    private function technicianHours(Carbon $from, Carbon $to): array
    {
        $rows = OperationalReportTotals::postedApprovedLineQuery($from, $to)
            ->where('repair_order_lines.type', RepairOrderLineType::Labor->value)
            ->groupBy('repair_orders.assigned_technician_id')
            ->selectRaw('repair_orders.assigned_technician_id as technician_id')
            ->selectRaw('COALESCE(SUM('.OperationalReportTotals::billedHoursSql().'), 0) as hours')
            ->selectRaw('COALESCE(SUM(repair_order_lines.subtotal_cents), 0) as labor_cents')
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $names = User::query()
            ->whereIn('id', $rows->pluck('technician_id')->filter())
            ->pluck('name', 'id');

        return $rows->map(function ($row) use ($names): array {
            $id = $row->technician_id;

            return [
                'name' => $id === null ? 'Unassigned' : (string) ($names[$id] ?? 'Assigned technician'),
                'hours' => number_format((float) $row->hours, 2),
                'labor' => $this->money((int) $row->labor_cents),
            ];
        })->all();
    }

    /**
     * @return array{title: string, note: string|null, rows: list<array{primary: string, secondary: string, meta: string, url: string|null}>}
     */
    private function openedRows(Carbon $from, Carbon $to, bool $link): array
    {
        $orders = $this->openedOrders($from, $to);

        return [
            'title' => 'Repair orders opened',
            'note' => 'Opened in this period. This is opportunity volume, not posted car count.',
            'rows' => $orders->map(fn (RepairOrder $repairOrder): array => $this->row(
                'RO '.$repairOrder->repair_order_id,
                $this->identity($repairOrder),
                'Opened '.$this->shopStamp($repairOrder->displayOpenedAt()),
                $link ? $repairOrder : null,
            ))->all(),
        ];
    }

    /**
     * @return array{title: string, note: string|null, rows: list<array{primary: string, secondary: string, meta: string, url: string|null}>}
     */
    private function closeRows(Carbon $from, Carbon $to, bool $link): array
    {
        $rows = [];

        foreach ($this->openedOrders($from, $to) as $repairOrder) {
            $money = $this->moneyFor($repairOrder);
            $presented = $money['approved'] + $money['recommended'] + $money['declined'] + $money['deferred'];
            if ($presented < 1 && $money['draft'] < 1) {
                continue;
            }
            $rows[] = $this->row(
                'RO '.$repairOrder->repair_order_id,
                $this->identity($repairOrder),
                'Approved '.$this->money($money['approved'])
                    .' · Recommended '.$this->money($money['recommended'])
                    .' · Deferred '.$this->money($money['deferred'])
                    .' · Declined '.$this->money($money['declined'])
                    .' · Draft '.$this->money($money['draft']),
                $link ? $repairOrder : null,
            );
        }

        return [
            'title' => 'Presented work',
            'note' => PresentedConcernPopulation::explanation(),
            'rows' => $rows,
        ];
    }

    /**
     * @return array{title: string, note: string|null, rows: list<array{primary: string, secondary: string, meta: string, url: string|null}>}
     */
    private function hourRows(Carbon $from, Carbon $to, bool $link): array
    {
        $lines = OperationalReportTotals::postedApprovedLineQuery($from, $to)
            ->where('repair_order_lines.type', RepairOrderLineType::Labor->value)
            ->leftJoin('customers', 'customers.id', '=', 'repair_orders.customer_id')
            ->orderBy('repair_orders.repair_order_id')
            ->get([
                'repair_orders.id',
                'repair_orders.repair_order_id',
                'repair_order_lines.description',
                'repair_order_lines.subtotal_cents',
                'repair_order_lines.labor_billed_hours',
                'repair_order_lines.quantity',
                'customers.first_name',
                'customers.last_name',
            ]);

        $rows = [];
        foreach ($lines as $line) {
            $hours = $line->labor_billed_hours !== null ? (float) $line->labor_billed_hours : (float) $line->quantity;
            $rows[] = [
                'primary' => 'RO '.$line->repair_order_id,
                'secondary' => trim(($line->first_name ?? '').' '.($line->last_name ?? '')).' · '.$line->description,
                'meta' => number_format($hours, 2).' h · '.$this->money((int) $line->subtotal_cents),
                'url' => $link ? route('operations.repair-orders.show', $line->repair_order_id) : null,
            ];
        }

        return [
            'title' => 'Posted labor',
            'note' => 'Billed hours on approved labor for repair orders posted in this period.',
            'rows' => $rows,
        ];
    }

    /**
     * @return array{title: string, note: string|null, rows: list<array{primary: string, secondary: string, meta: string, url: string|null}>}
     */
    private function cycleRows(Carbon $from, Carbon $to, bool $link): array
    {
        $orders = OperationalReportDateScope::salesPostedBetween(
            RepairOrder::query()->with(['customer:id,first_name,last_name', 'vehicle:id,year,make,model']),
            $from,
            $to,
        )->get();

        $rows = $orders->map(function (RepairOrder $repairOrder) use ($link): array {
            $days = $this->cycleLength($repairOrder);

            return $this->row(
                'RO '.$repairOrder->repair_order_id,
                $this->identity($repairOrder),
                $days === null ? 'Cycle unavailable' : number_format($days, 1).' days, opened to posted',
                $link ? $repairOrder : null,
            );
        })->sortByDesc(fn (array $row): string => $row['meta'])->values()->all();

        return [
            'title' => 'Posted cycle time',
            'note' => 'Median is the primary number. Average is pulled up by long jobs.',
            'rows' => $rows,
        ];
    }

    /**
     * @return array{title: string, note: string|null, rows: list<array{primary: string, secondary: string, meta: string, url: string|null}>}
     */
    private function backlogRows(bool $authorized, bool $link): array
    {
        $rows = [];

        foreach ($this->openRepairOrders() as $repairOrder) {
            $money = $this->moneyFor($repairOrder);
            $notAuthorized = $money['draft'] + $money['recommended'] + $money['deferred'];
            if ($authorized && $money['approved'] < 1) {
                continue;
            }
            if (! $authorized && $notAuthorized < 1) {
                continue;
            }
            $rows[] = $this->row(
                'RO '.$repairOrder->repair_order_id,
                $this->identity($repairOrder),
                $authorized
                    ? 'Authorized '.$this->money($money['approved'])
                    : 'Draft '.$this->money($money['draft']).' · Recommended '.$this->money($money['recommended']).' · Deferred '.$this->money($money['deferred']),
                $link ? $repairOrder : null,
            );
        }

        return [
            'title' => $authorized ? 'Authorized backlog' : 'Not authorized',
            'note' => $authorized
                ? 'Approved pre-tax dollars on open repair orders.'
                : 'Draft, recommended, and deferred dollars on open repair orders. Declined work is not backlog.',
            'rows' => $rows,
        ];
    }

    /**
     * @return array{title: string, note: string|null, rows: list<array{primary: string, secondary: string, meta: string, url: string|null}>}
     */
    private function agingRows(int $days, bool $link): array
    {
        $days = in_array($days, self::AGING_DAYS, true) ? $days : 10;
        $rows = [];

        foreach ($this->openRepairOrders() as $repairOrder) {
            $age = $this->ageDays($repairOrder);
            if ($age <= $days) {
                continue;
            }
            $rows[] = $this->row(
                'RO '.$repairOrder->repair_order_id,
                $this->identity($repairOrder),
                number_format($age, 1).' days open · '.$repairOrder->status->label(),
                $link ? $repairOrder : null,
            );
        }

        return [
            'title' => 'Open more than '.$days.' days',
            'note' => 'Age is from opened date on repair orders that are not closed.',
            'rows' => $rows,
        ];
    }

    /**
     * @return array{title: string, note: string|null, rows: list<array{primary: string, secondary: string, meta: string, url: string|null}>}
     */
    private function partRows(Carbon $from, Carbon $to, bool $link): array
    {
        $lines = OperationalReportTotals::postedApprovedLineQuery($from, $to)
            ->where('repair_order_lines.type', RepairOrderLineType::Part->value)
            ->whereNotNull('repair_order_lines.part_cost_cents')
            ->where('repair_order_lines.subtotal_cents', '>', 0)
            ->get([
                'repair_orders.repair_order_id',
                'repair_order_lines.description',
                'repair_order_lines.vendor_name',
                'repair_order_lines.part_cost_cents',
                'repair_order_lines.quantity',
                'repair_order_lines.subtotal_cents',
                'repair_order_lines.pricing_matrix_name',
                'repair_order_lines.matrix_applied',
                'repair_order_lines.is_overridden',
            ]);

        $rows = [];
        foreach ($lines as $line) {
            $sale = (int) $line->subtotal_cents;
            $cost = (int) round(((float) $line->quantity) * (int) $line->part_cost_cents);
            $gp = $sale - $cost;
            $margin = (int) round(($gp / $sale) * 100);
            $source = $line->is_overridden ? 'Override' : ($line->pricing_matrix_name ?: ($line->matrix_applied ? 'Matrix' : 'No matrix recorded'));
            $rows[] = [
                'primary' => 'RO '.$line->repair_order_id,
                'secondary' => $line->description.($line->vendor_name ? ' · '.$line->vendor_name : ''),
                'meta' => 'Cost '.$this->money($cost).' · Sale '.$this->money($sale).' · GP '.$this->money($gp).' · '.$margin.'% · '.$source,
                'url' => $link ? route('operations.repair-orders.show', $line->repair_order_id) : null,
                'margin' => $margin,
            ];
        }

        usort($rows, fn (array $left, array $right): int => $left['margin'] <=> $right['margin']);
        $rows = array_map(function (array $row): array {
            unset($row['margin']);

            return $row;
        }, $rows);

        return [
            'title' => 'Parts lines',
            'note' => 'Posted approved parts with a cost. Lowest margin first. Lines missing a cost are excluded from the buckets and called out on the margin card.',
            'rows' => $rows,
        ];
    }

    /**
     * @return array{title: string, note: string|null, rows: list<array{primary: string, secondary: string, meta: string, url: string|null}>}
     */
    private function queueRows(string $queue, bool $link): array
    {
        $open = $this->openRepairOrders();
        $contacts = $this->contactsFor($open->pluck('id'));
        $targets = ShopExcellenceTargets::current();
        $agingThreshold = (float) ($targets['median_cycle_target_days'] ?? 5);
        $stall = $this->stallRule($targets);
        $rows = [];

        foreach ($open as $repairOrder) {
            $money = $this->moneyFor($repairOrder);
            $status = $repairOrder->status->enum();
            $age = $this->ageDays($repairOrder);
            $sent = isset($contacts['sent'][$repairOrder->id]);
            $include = match ($queue) {
                'presentation' => in_array($status, [RepairOrderStatus::Draft, RepairOrderStatus::Estimate], true)
                    && ($money['draft'] > 0 || $money['recommended'] > 0)
                    && ! $sent,
                'follow_up' => $money['recommended'] > 0 || $status === RepairOrderStatus::WaitingApproval,
                'pickup' => in_array($status, [RepairOrderStatus::ReadyPickup, RepairOrderStatus::Completed, RepairOrderStatus::Invoiced], true),
                'stalled' => $stall !== null && $age >= $stall['days'] && ! $this->isActiveProduction($status),
                default => in_array($status, [RepairOrderStatus::Approved, RepairOrderStatus::ReadyForWork, RepairOrderStatus::InProgress, RepairOrderStatus::WaitingParts], true)
                    && $age > $agingThreshold,
            };

            if (! $include) {
                continue;
            }

            $contact = $contacts['latest'][$repairOrder->id] ?? null;
            $contactLabel = $contact instanceof CommunicationEvent
                ? $contact->event_type->label().' '.$this->shopStamp($contact->occurred_at)
                : 'No estimate contact on file';
            $owner = $repairOrder->assignedTechnician?->name ?: 'No owner on the repair order';
            $dollars = match ($queue) {
                'follow_up', 'presentation' => $this->money($money['recommended'] > 0 ? $money['recommended'] : $money['draft']),
                'pickup' => $this->pickupBalanceLabel($repairOrder),
                default => $this->money($money['approved']),
            };

            $rows[] = [
                'cents' => $money['recommended'] > 0 ? $money['recommended'] : $money['draft'] + $money['approved'],
                'age' => $age,
                'row' => $this->row(
                    'RO '.$repairOrder->repair_order_id,
                    $this->identity($repairOrder),
                    $dollars.' · '.number_format($age, 1).' days · '.$owner.' · '.$contactLabel,
                    $link ? $repairOrder : null,
                ),
            ];
        }

        if ($queue === 'follow_up') {
            usort($rows, function (array $left, array $right): int {
                return [$right['cents'], $right['age']] <=> [$left['cents'], $left['age']];
            });
        }

        $rows = array_map(static fn (array $entry): array => $entry['row'], $rows);

        $titles = [
            'presentation' => 'Presentation / decision needed',
            'follow_up' => 'Follow-up',
            'pickup' => 'Ready for pickup',
            'stalled' => 'Stalled',
        ];

        return [
            'title' => $titles[$queue] ?? 'Aging authorized work',
            'note' => match ($queue) {
                'presentation' => 'No estimate link is on file. ARK is not claiming the estimate was never presented in person.',
                'follow_up' => 'Largest recommended dollars first. Contact time comes only from communication events on the repair order.',
                'stalled' => $stall === null
                    ? 'No stall age is set. Set a stall age, or a median cycle target, before a repair order can be marked stalled.'
                    : $stall['hint'],
                default => null,
            },
            'rows' => $rows,
        ];
    }

    /**
     * @return array{title: string, note: string|null, rows: list<array{primary: string, secondary: string, meta: string, url: string|null}>}
     */
    private function lostRows(Carbon $from, Carbon $to, bool $link): array
    {
        $rows = [];

        foreach ($this->lostRepairOrders($from, $to) as $repairOrder) {
            $recommended = $this->moneyFor($repairOrder)['recommended'];
            $rows[] = $this->row(
                'RO '.$repairOrder->repair_order_id,
                $this->identity($repairOrder),
                ($repairOrder->lost_reason_key?->label() ?? 'Lost').' · recommended '.$this->money($recommended),
                $link ? $repairOrder : null,
            );
        }

        return [
            'title' => 'Lost repair orders',
            'note' => 'Recommended dollars still on lost repair orders. This is follow-up volume, not money assumed recoverable.',
            'rows' => $rows,
        ];
    }

    /**
     * @return Collection<int, RepairOrder>
     */
    /**
     * Current cars in the building. Period rates keep the reporting floor.
     * This list does not, so an old open repair order stays visible.
     *
     * @return Collection<int, RepairOrder>
     */
    private function openRepairOrders(): Collection
    {
        return RepairOrder::query()
            ->with([
                'lines.concern',
                'customer:id,first_name,last_name',
                'vehicle:id,year,make,model',
                'assignedTechnician:id,name',
            ])
            ->where('status', '!=', RepairOrderStatus::Closed->value)
            ->orderBy('repair_order_id')
            ->get()
            ->filter(fn (RepairOrder $repairOrder): bool => ! $repairOrder->status->isTerminal() && $repairOrder->close_variant_key !== 'lost')
            ->values();
    }

    /**
     * @param  array<string, mixed>  $targets
     * @return array{days: float, hint: string}|null
     */
    private function stallRule(array $targets): ?array
    {
        $explicit = $targets['stalled_ro_age_days'] ?? null;
        if ($explicit !== null && (float) $explicit > 0) {
            $days = (float) $explicit;

            return [
                'days' => $days,
                'hint' => 'Open '.number_format($days, 1).' days or more, and not in production, parts, quality check, or pickup.',
            ];
        }

        $cycle = $targets['median_cycle_target_days'] ?? null;
        if ($cycle !== null && (float) $cycle > 0) {
            $days = (float) $cycle;

            return [
                'days' => $days,
                'hint' => 'Open longer than the '.number_format($days, 1).' day cycle target, and not in production, parts, quality check, or pickup.',
            ];
        }

        return null;
    }

    private function isActiveProduction(RepairOrderStatus $status): bool
    {
        return in_array($status, [
            RepairOrderStatus::InProgress,
            RepairOrderStatus::WaitingParts,
            RepairOrderStatus::QualityCheck,
            RepairOrderStatus::ReadyPickup,
            RepairOrderStatus::ReadyForWork,
            RepairOrderStatus::Completed,
            RepairOrderStatus::Invoiced,
        ], true);
    }

    /**
     * @return Collection<int, RepairOrder>
     */
    private function openedOrders(Carbon $from, Carbon $to): Collection
    {
        return OperationalReportDateScope::openedBetween(
            RepairOrder::query()->with([
                'lines.concern',
                'customer:id,first_name,last_name',
                'vehicle:id,year,make,model',
            ]),
            $from,
            $to,
        )->orderBy('repair_order_id')->get();
    }

    /**
     * @return Collection<int, RepairOrder>
     */
    private function lostRepairOrders(Carbon $from, Carbon $to): Collection
    {
        return RepairOrder::query()
            ->with([
                'lines.concern',
                'customer:id,first_name,last_name',
                'vehicle:id,year,make,model',
            ])
            ->where('close_variant_key', 'lost')
            ->whereBetween('lost_reason_recorded_at', [$from, $to])
            ->orderBy('repair_order_id')
            ->get();
    }

    /**
     * @param  Collection<int, int>  $repairOrderIds
     * @return array{sent: array<int, true>, latest: array<int, CommunicationEvent>}
     */
    private function contactsFor(Collection $repairOrderIds): array
    {
        if ($repairOrderIds->isEmpty()) {
            return ['sent' => [], 'latest' => []];
        }

        $events = CommunicationEvent::query()
            ->whereIn('repair_order_id', $repairOrderIds->all())
            ->whereIn('event_type', array_map(fn (OperationalCommunicationType $type): string => $type->value, self::CONTACT_TYPES))
            ->get(['repair_order_id', 'event_type', 'occurred_at']);

        $sent = [];
        $latest = [];

        foreach ($events as $event) {
            if ($event->event_type === OperationalCommunicationType::EstimateSent) {
                $sent[$event->repair_order_id] = true;
            }
            $current = $latest[$event->repair_order_id] ?? null;
            if (! $current instanceof CommunicationEvent || $event->occurred_at->greaterThan($current->occurred_at)) {
                $latest[$event->repair_order_id] = $event;
            }
        }

        return ['sent' => $sent, 'latest' => $latest];
    }

    /**
     * @return array{approved: int, recommended: int, declined: int, deferred: int, draft: int}
     */
    private function moneyFor(RepairOrder $repairOrder): array
    {
        return [
            'approved' => $this->calculator->approvedTotalsForRead($repairOrder)->subtotalBeforeTaxCents(),
            'recommended' => $this->calculator->recommendedTotalsForRead($repairOrder)->subtotalBeforeTaxCents(),
            'declined' => $this->calculator->declinedTotalsForRead($repairOrder)->subtotalBeforeTaxCents(),
            'deferred' => $this->calculator->deferredTotalsForRead($repairOrder)->subtotalBeforeTaxCents(),
            'draft' => $this->calculator->draftTotalsForRead($repairOrder)->subtotalBeforeTaxCents(),
        ];
    }

    private function soldHours(Carbon $from, Carbon $to): float
    {
        return (float) OperationalReportTotals::postedApprovedLineQuery($from, $to)
            ->where('repair_order_lines.type', RepairOrderLineType::Labor->value)
            ->selectRaw('COALESCE(SUM('.OperationalReportTotals::billedHoursSql().'), 0) as hours')
            ->value('hours');
    }

    /**
     * @return list<float>
     */
    private function cycleDays(Carbon $from, Carbon $to): array
    {
        return OperationalReportDateScope::salesPostedBetween(RepairOrder::query(), $from, $to)
            ->get(['opened_at', 'created_at', 'posted_at'])
            ->map(fn (RepairOrder $repairOrder): ?float => $this->cycleLength($repairOrder))
            ->filter(fn (?float $days): bool => $days !== null)
            ->values()
            ->all();
    }

    private function cycleLength(RepairOrder $repairOrder): ?float
    {
        if ($repairOrder->posted_at === null) {
            return null;
        }

        $opened = $repairOrder->displayOpenedAt();

        return max(0, ($repairOrder->posted_at->getTimestamp() - $opened->getTimestamp()) / 86400);
    }

    private function ageDays(RepairOrder $repairOrder): float
    {
        return max(0, (OperationalReportDateScope::shopNow()->getTimestamp() - $repairOrder->displayOpenedAt()->getTimestamp()) / 86400);
    }

    private function pickupBalanceLabel(RepairOrder $repairOrder): string
    {
        $balance = $repairOrder->balanceDue();

        if (! $balance->hasIssuedInvoice) {
            return 'No invoice';
        }

        return 'Due '.$this->money($balance->balanceDueCents);
    }

    private function identity(RepairOrder $repairOrder): string
    {
        $customer = $repairOrder->customer?->name ?: 'Customer';
        $vehicle = $repairOrder->vehicle?->display_name ?: 'Vehicle';

        return $customer.' · '.$vehicle;
    }

    /**
     * @return array{primary: string, secondary: string, meta: string, url: string|null}
     */
    private function row(string $primary, string $secondary, string $meta, ?RepairOrder $repairOrder): array
    {
        return [
            'primary' => $primary,
            'secondary' => $secondary,
            'meta' => $meta,
            'url' => $repairOrder !== null ? route('operations.repair-orders.show', $repairOrder) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function kpi(
        string $key,
        string $label,
        string $value,
        string $hint,
        ?float $target,
        ?string $tone,
        ?string $trend,
        ?string $trendCaption,
        string $focus,
        bool $financial,
        string $targetSuffix = '',
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'value' => $value,
            'hint' => $hint,
            'target' => $target,
            'target_label' => $target !== null ? 'Target '.rtrim(rtrim(number_format($target, 2), '0'), '.').$targetSuffix : 'No target',
            'tone' => $tone,
            'trend' => $trend,
            'trend_caption' => $trendCaption,
            'focus' => $focus,
            'financial' => $financial,
        ];
    }

    private function trend(?float $current, ?float $previous, int $decimals = 2): ?string
    {
        if ($current === null || $previous === null) {
            return null;
        }

        $delta = round($current - $previous, $decimals);
        $formatted = number_format($delta, $decimals);

        return $delta > 0 ? '+'.$formatted : $formatted;
    }

    private function money(int $cents): string
    {
        return Money::ofMinor($cents, 'USD')->formatTo('en_US');
    }

    private function shopStamp(Carbon $instant): string
    {
        return $instant->copy()->timezone(OperationalReportDateScope::displayTimezone())->format('M j, g:i A');
    }
}
