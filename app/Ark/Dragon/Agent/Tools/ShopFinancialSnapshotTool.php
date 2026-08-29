<?php

namespace App\Ark\Dragon\Agent\Tools;

use App\Ark\Dragon\Agent\DragonAgentTool;
use App\Ark\Operations\RepairOrders\ApprovalForecastProjection;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Reports\OperationalReportRangeMetrics;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use Illuminate\Support\Carbon;

final class ShopFinancialSnapshotTool implements DragonAgentTool
{
    public function __construct(private readonly ApprovalForecastProjection $approvals) {}

    public function name(): string
    {
        return 'shop.financial_snapshot';
    }

    public function description(): string
    {
        return 'Live Demo Auto Repair operational money: posted sales, cash collected, labor/parts sales, and waiting-approval dollars. Use for today, this month (shop MTD), or a named calendar month that is on or before the shop clock. The shop clock in the system prompt is the date — never call the current month/year the future. Does not provide net profit. Keep posted sales, cash collected, waiting-approval dollars, and profit distinct.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'range' => [
                    'type' => 'string',
                    'enum' => ['today', 'this_month'],
                    'description' => 'today = shop-local day. this_month = shop-local month through today. Omit if year+month are set.',
                ],
                'year' => [
                    'type' => 'integer',
                    'description' => 'Calendar year for a named month (shop-local). Must be <= shop clock year.',
                ],
                'month' => [
                    'type' => 'integer',
                    'description' => 'Calendar month 1-12 for a named month. Combined with year. Current month is MTD, not a future period.',
                ],
            ],
        ];
    }

    public function invoke(array $arguments): array
    {
        $tz = ShopDisplayTimezone::resolve();
        $now = ShopDisplayTimezone::now();
        $window = $this->window($arguments, $now, $tz);
        if (isset($window['ok']) && $window['ok'] === false) {
            return $window + [
                '_ark_categories' => ['financial_operational_report'],
                'shop_clock' => $this->clockPayload($now, $tz),
            ];
        }

        $metrics = new OperationalReportRangeMetrics($window['from_utc'], $window['to_utc']);

        $waiting = RepairOrder::query()
            ->where('status', RepairOrderStatus::WaitingApproval)
            ->with(['concerns.lines', 'lines.concern'])
            ->get();

        $pendingCents = 0;
        $approvedCents = 0;
        foreach ($waiting as $repairOrder) {
            $forecast = $this->approvals->for($repairOrder);
            $pendingCents += (int) $forecast['pending_cents'];
            $approvedCents += (int) $forecast['approved_cents'];
        }

        $kpis = [];
        foreach ($metrics->kpis() as $kpi) {
            $kpis[] = [
                'label' => $kpi['label'],
                'value' => $kpi['value'],
                'definition' => $kpi['hint'],
            ];
        }

        return [
            'ok' => true,
            '_ark_categories' => ['financial_operational_report', 'approval_forecast'],
            'range' => $window['label'],
            'timezone' => $tz,
            'shop_clock' => $this->clockPayload($now, $tz),
            'period_local' => [
                'from' => $window['from_local']->toDateString(),
                'to' => $window['to_local']->toDateString(),
            ],
            'definitions' => [
                'posted_sales' => 'Operational Report “Sales Posted” — Tekmetric EOD posted ROs in range. Not cash in the drawer.',
                'cash_collected' => 'Operational Report “Cash Collected” — payments + deposits cashiered in range.',
                'waiting_approval_dollars' => 'Human-readable US dollars of Approval Forecast pending on ROs currently waiting approval. Do not confuse with pending_recommended_cents (pennies). Estimate language, not collected cash. Waiting-approval is live board, not limited to this range.',
                'net_profit' => 'Not available. ARK Financial Authority is RED; Dragon will not invent P&L.',
            ],
            'kpis' => $kpis,
            'waiting_approval' => [
                'repair_order_count' => $waiting->count(),
                'pending_recommended_cents' => $pendingCents,
                'pending_recommended_dollars' => round($pendingCents / 100, 2),
                'pending_recommended_display' => '$'.number_format($pendingCents / 100, 2),
                'already_approved_cents_on_those_ros' => $approvedCents,
            ],
            'cannot_answer' => [
                'net_profit',
                'compared_with_normal_until_a_baseline_is_named',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function window(array $arguments, Carbon $now, string $tz): array
    {
        $year = isset($arguments['year']) ? (int) $arguments['year'] : null;
        $month = isset($arguments['month']) ? (int) $arguments['month'] : null;
        $range = (string) ($arguments['range'] ?? '');

        if ($year !== null || $month !== null) {
            if ($year === null || $month < 1 || $month > 12) {
                return [
                    'ok' => false,
                    'error' => 'Named month needs year and month (1-12).',
                ];
            }

            $start = Carbon::create($year, $month, 1, 0, 0, 0, $tz);
            if ($start->startOfMonth()->gt($now->copy()->startOfMonth())) {
                return [
                    'ok' => false,
                    'error' => 'That month is after the shop clock. It is not in the future relative to training data — it has not happened at this shop yet. Use this_month or a month on or before '.$now->format('F Y').'.',
                ];
            }

            $end = $start->copy()->endOfMonth();
            if ($start->isSameMonth($now)) {
                $end = $now->copy()->endOfDay();
            }

            return [
                'label' => $start->isSameMonth($now) ? 'named_month_mtd_shop_local' : 'named_month_shop_local',
                'from_local' => $start->copy()->startOfDay(),
                'to_local' => $end,
                'from_utc' => $start->copy()->startOfDay()->utc(),
                'to_utc' => $end->copy()->utc(),
            ];
        }

        if ($range === 'this_month') {
            $start = $now->copy()->startOfMonth();
            $end = $now->copy()->endOfDay();

            return [
                'label' => 'this_month_shop_local',
                'from_local' => $start,
                'to_local' => $end,
                'from_utc' => $start->copy()->utc(),
                'to_utc' => $end->copy()->utc(),
            ];
        }

        $start = $now->copy()->startOfDay();
        $end = $now->copy()->endOfDay();

        return [
            'label' => 'today_shop_local',
            'from_local' => $start,
            'to_local' => $end,
            'from_utc' => $start->copy()->utc(),
            'to_utc' => $end->copy()->utc(),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function clockPayload(Carbon $now, string $tz): array
    {
        return [
            'timezone' => $tz,
            'date' => $now->toDateString(),
            'display' => $now->format('l, F j, Y'),
            'month' => $now->format('F Y'),
        ];
    }
}
