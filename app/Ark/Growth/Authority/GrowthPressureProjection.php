<?php

namespace App\Ark\Growth\Authority;

use App\Ark\Growth\Integrations\SearchMetricsAggregator;
use App\Ark\Growth\Models\GrowthLocationMetric;
use App\Ark\Growth\Models\GrowthSearchQuery;
use App\Ark\Operations\Leads\Public\PublicSurfaceSettings;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use Illuminate\Support\Carbon;

/**
 * Market Pressure — selection, retention, and reputation signals.
 *
 * Same grammar as Customer Decision Pressure: what → why → evidence.
 * Not a marketing dashboard. A projection of market authority health.
 */
final class GrowthPressureProjection
{
    public function __construct(
        private readonly SearchMetricsAggregator $searchMetrics,
        private readonly AuthorityLedgerProjection $ledger,
        private readonly MarketPressureAdvisorBreakdown $advisorBreakdown,
    ) {}

    /**
     * @return array{
     *     period_label: string,
     *     diagnosis: string|null,
     *     rows: list<array<string, mixed>>,
     *     ledger: array{effort: array<string, mixed>, earned: array<string, mixed>},
     *     missed_review_opportunities: list<array<string, mixed>>
     * }
     */
    public function resolve(?Carbon $through = null): array
    {
        $through ??= now();
        $rows = [
            $this->missedReviewOpportunitiesRow($through),
            $this->reviewRequestRateRow($through),
            $this->reviewVelocityRow($through),
            $this->newCustomerMixRow($through),
            $this->brandSearchTrendRow($through),
            $this->gbpMetricTrendRow('CALL_CLICKS', 'Maps calls', $through),
            $this->gbpMetricTrendRow('BUSINESS_DIRECTION_REQUESTS', 'Direction requests', $through),
            $this->websiteCtrRow($through),
        ];

        $pressureRows = collect($rows)->where('posture', 'pressure')->count();
        $diagnosis = $this->diagnosis($pressureRows, $rows);

        return [
            'period_label' => 'Last 28 days',
            'diagnosis' => $diagnosis,
            'rows' => $rows,
            'ledger' => $this->ledger->resolve($through),
            'missed_review_opportunities' => $this->missedReviewOpportunityDetails($through),
        ];
    }

    /**
     * Owner Today summary — market pressure before the first phone call.
     *
     * @return array{
     *     posture: string,
     *     why_care: string,
     *     headline: string,
     *     eligible_closes: int,
     *     review_requests: int,
     *     target: int,
     *     missed_count: int,
     *     advisor_breakdown: list<array<string, mixed>>,
     *     detail_url: string
     * }
     */
    public function forOwnerToday(?Carbon $through = null): array
    {
        $through ??= now();
        $windowDays = (int) config('growth_authority.pressure.missed_review_opportunity_days', 30);
        $from = $through->copy()->subDays($windowDays)->startOfDay();

        $eligible = $this->eligiblePaidClosesQuery($from, $through)->get();
        $eligibleCount = $eligible->count();
        $reviewRequests = $eligible->where('review_request_sent', true)->count();
        $missedCount = $eligible->filter(fn (RepairOrder $repairOrder): bool => $repairOrder->review_request_sent !== true)->count();

        $posture = $missedCount > 0 ? 'pressure' : 'healthy';

        return [
            'posture' => $posture,
            'why_care' => $this->whyCare('missed_review_opportunities'),
            'headline' => $eligibleCount > 0
                ? "{$eligibleCount} paid repair orders in the last {$windowDays} days. Only {$reviewRequests} review requests recorded."
                : 'No paid closes in the review capture window yet.',
            'eligible_closes' => $eligibleCount,
            'review_requests' => $reviewRequests,
            'target' => $eligibleCount,
            'missed_count' => $missedCount,
            'advisor_breakdown' => $this->advisorBreakdown->forPeriod($from, $through),
            'detail_url' => route('website.performance').'#market-pressure',
        ];
    }

    private function whyCare(string $key): string
    {
        return (string) config("growth_authority.why_care.{$key}", '');
    }

    /**
     * @return array<string, mixed>
     */
    private function missedReviewOpportunitiesRow(Carbon $through): array
    {
        $windowDays = (int) config('growth_authority.pressure.missed_review_opportunity_days', 30);
        $from = $through->copy()->subDays($windowDays)->startOfDay();
        $missed = $this->missedReviewOpportunityDetails($through, 5);
        $eligible = $this->eligiblePaidClosesQuery($from, $through)->count();
        $missedCount = $this->eligiblePaidClosesQuery($from, $through)
            ->get()
            ->filter(fn (RepairOrder $repairOrder): bool => $repairOrder->review_request_sent !== true)
            ->count();

        return [
            'key' => 'missed_review_opportunities',
            'label' => 'Missed review opportunities',
            'posture' => $missedCount > 0 ? 'pressure' : 'healthy',
            'headline' => $missedCount > 0
                ? "{$missedCount} paid closes without a review request"
                : 'Every paid close recorded a review request',
            'why' => 'Customers leave without becoming part of your reputation when no review is asked at close.',
            'why_care' => $this->whyCare('missed_review_opportunities'),
            'evidence' => [
                ['label' => 'Paid closes ('.$windowDays.'d)', 'value' => (string) $eligible],
                ['label' => 'Missed', 'value' => (string) $missedCount],
                ['label' => 'Target', 'value' => '0 missed'],
            ],
            'detail_rows' => $missed,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function reviewRequestRateRow(Carbon $through): array
    {
        $from = $through->copy()->subDays(28)->startOfDay();
        $eligible = $this->eligiblePaidClosesQuery($from, $through)->get();
        $recorded = $eligible->whereNotNull('review_request_sent');
        $sent = $recorded->where('review_request_sent', true)->count();
        $totalRecorded = $recorded->count();
        $rate = $totalRecorded > 0 ? $sent / $totalRecorded : null;
        $target = (float) config('growth_authority.targets.review_request_rate', 1.0);
        $belowTarget = $rate !== null && $rate < $target;

        return [
            'key' => 'review_request_rate',
            'label' => 'Review request rate',
            'posture' => $belowTarget || $totalRecorded < $eligible->count() ? 'pressure' : 'healthy',
            'headline' => $rate === null
                ? 'No review requests recorded yet'
                : sprintf('%d%% of closes logged a review ask', (int) round($rate * 100)),
            'why' => 'ARK only knows what you record at close. Unlogged closes look like missed reputation opportunities.',
            'why_care' => $this->whyCare('review_request_rate'),
            'evidence' => [
                ['label' => 'Paid closes (28d)', 'value' => (string) $eligible->count()],
                ['label' => 'Requests sent', 'value' => (string) $sent],
                ['label' => 'Target', 'value' => (int) round($target * 100).'% logged'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function reviewVelocityRow(Carbon $through): array
    {
        $public = PublicSurfaceSettings::current();
        $current = (int) ($public['google_review_count'] ?? 0);
        $target = (int) config('growth_authority.targets.reviews_per_month', 12);

        return [
            'key' => 'review_velocity',
            'label' => 'Review velocity',
            'posture' => 'observe',
            'headline' => "{$current} Google reviews on file",
            'why' => 'Review count is entered manually until review sync ships. Update Website → Manage when Maps count changes.',
            'why_care' => $this->whyCare('review_velocity'),
            'evidence' => [
                ['label' => 'Current count', 'value' => (string) $current],
                ['label' => 'Monthly target', 'value' => (string) $target],
                ['label' => 'Capture target', 'value' => (int) (config('growth_authority.targets.review_capture_rate', 0.2) * 100).'%'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function newCustomerMixRow(Carbon $through): array
    {
        $from = $through->copy()->subDays(28)->startOfDay();
        $closed = RepairOrder::query()
            ->where('status', RepairOrderStatus::Closed)
            ->where('close_variant_key', 'paid')
            ->where('closed_at', '>=', $from)
            ->where('closed_at', '<=', $through)
            ->with('customer')
            ->get();

        $total = $closed->count();
        $newCustomerRos = $closed->filter(function (RepairOrder $repairOrder) use ($from): bool {
            if ($repairOrder->customer === null) {
                return false;
            }

            return $repairOrder->customer->created_at >= $from;
        })->count();

        $mix = $total > 0 ? $newCustomerRos / $total : null;
        $target = (float) config('growth_authority.targets.new_customer_mix_min', 0.25);
        $lowMix = $mix !== null && $mix < $target;

        return [
            'key' => 'new_customer_mix',
            'label' => 'New customer mix',
            'posture' => $lowMix ? 'pressure' : 'observe',
            'headline' => $mix === null
                ? 'No paid closes in window'
                : sprintf('%d%% of ROs are first-time customers', (int) round($mix * 100)),
            'why' => 'Selection and retention — were you chosen by new drivers, or mostly serving repeat relationships?',
            'why_care' => $this->whyCare('new_customer_mix'),
            'evidence' => [
                ['label' => 'Paid closes (28d)', 'value' => (string) $total],
                ['label' => 'First-time customer ROs', 'value' => (string) $newCustomerRos],
                ['label' => 'Floor target', 'value' => (int) round($target * 100).'%'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function brandSearchTrendRow(Carbon $through): array
    {
        $end = $through->copy()->endOfDay();
        $thisStart = $end->copy()->subDays(6)->startOfDay();
        $previousStart = $end->copy()->subDays(13)->startOfDay();
        $previousEnd = $end->copy()->subDays(7)->endOfDay();

        $brandPattern = fn ($query) => $query->where(function ($inner): void {
            $inner->where('query', 'like', '%lugs%')
                ->orWhere('query', 'like', '%plugs%');
        });

        $current = (int) $brandPattern(GrowthSearchQuery::query())
            ->whereDate('report_date', '>=', $thisStart->toDateString())
            ->whereDate('report_date', '<=', $end->toDateString())
            ->sum('impressions');

        $previous = (int) $brandPattern(GrowthSearchQuery::query())
            ->whereDate('report_date', '>=', $previousStart->toDateString())
            ->whereDate('report_date', '<=', $previousEnd->toDateString())
            ->sum('impressions');

        return $this->trendRow(
            key: 'brand_searches',
            label: 'Brand searches',
            current: $current,
            previous: $previous,
            why: 'People searching your name — selection after they already know you.',
            whyCareKey: 'brand_searches',
            unit: 'impressions',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function gbpMetricTrendRow(string $metric, string $label, Carbon $through): array
    {
        $end = $through->copy()->endOfDay();
        $thisStart = $end->copy()->subDays(6)->startOfDay();
        $previousStart = $end->copy()->subDays(13)->startOfDay();
        $previousEnd = $end->copy()->subDays(7)->endOfDay();

        $current = (int) GrowthLocationMetric::query()
            ->where('metric', $metric)
            ->whereDate('report_date', '>=', $thisStart->toDateString())
            ->whereDate('report_date', '<=', $end->toDateString())
            ->sum('value');

        $previous = (int) GrowthLocationMetric::query()
            ->where('metric', $metric)
            ->whereDate('report_date', '>=', $previousStart->toDateString())
            ->whereDate('report_date', '<=', $previousEnd->toDateString())
            ->sum('value');

        return $this->trendRow(
            key: strtolower($metric),
            label: $label,
            current: $current,
            previous: $previous,
            why: 'Maps selection signal — did searchers call or ask for directions?',
            whyCareKey: strtolower($metric),
            unit: 'events',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function websiteCtrRow(Carbon $through): array
    {
        $aggregated = $this->searchMetrics->aggregatedQueries($through);
        $impressions = (int) $aggregated->sum('impressions');
        $clicks = (int) $aggregated->sum('clicks');
        $ctr = $impressions > 0 ? $clicks / $impressions : null;

        return [
            'key' => 'website_ctr',
            'label' => 'Website CTR',
            'posture' => 'observe',
            'headline' => $ctr === null
                ? 'Awaiting Search Console data'
                : sprintf('%.1f%% click-through (28d)', $ctr * 100),
            'why' => 'Selection on organic search — title and position, not shop quality once they arrive.',
            'why_care' => $this->whyCare('website_ctr'),
            'evidence' => [
                ['label' => 'Impressions (28d)', 'value' => number_format($impressions)],
                ['label' => 'Clicks (28d)', 'value' => number_format($clicks)],
                ['label' => 'CTR', 'value' => $ctr !== null ? sprintf('%.1f%%', $ctr * 100) : '—'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function trendRow(
        string $key,
        string $label,
        int $current,
        int $previous,
        string $why,
        string $unit,
        ?string $whyCareKey = null,
    ): array {
        if ($previous === 0 && $current === 0) {
            return [
                'key' => $key,
                'label' => $label,
                'posture' => 'observe',
                'headline' => 'No data yet',
                'why' => $why,
                'why_care' => $whyCareKey !== null ? $this->whyCare($whyCareKey) : '',
                'evidence' => [
                    ['label' => 'Last 7 days', 'value' => '0'],
                    ['label' => 'Prior 7 days', 'value' => '0'],
                ],
            ];
        }

        $change = $previous > 0
            ? (int) round((($current - $previous) / $previous) * 100)
            : null;

        $posture = 'observe';
        if ($change !== null && $change <= -10) {
            $posture = 'pressure';
        }

        return [
            'key' => $key,
            'label' => $label,
            'posture' => $posture,
            'headline' => $change === null
                ? "↑ New {$unit}"
                : (($change >= 0 ? '↑ ' : '↓ ').abs($change)."% vs prior week"),
            'why' => $why,
            'why_care' => $whyCareKey !== null ? $this->whyCare($whyCareKey) : '',
            'evidence' => [
                ['label' => 'Last 7 days', 'value' => number_format($current)],
                ['label' => 'Prior 7 days', 'value' => number_format($previous)],
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function missedReviewOpportunityDetails(Carbon $through, int $limit = 10): array
    {
        $windowDays = (int) config('growth_authority.pressure.missed_review_opportunity_days', 30);
        $from = $through->copy()->subDays($windowDays)->startOfDay();

        return $this->eligiblePaidClosesQuery($from, $through)
            ->with(['customer', 'vehicle'])
            ->latest('closed_at')
            ->get()
            ->filter(fn (RepairOrder $repairOrder): bool => $repairOrder->review_request_sent !== true)
            ->take($limit)
            ->map(function (RepairOrder $repairOrder): array {
                $customer = $repairOrder->customer;
                $hasEmail = filled($customer?->email);

                return [
                    'repair_order_id' => $repairOrder->repair_order_id,
                    'label' => 'RO #'.$repairOrder->repair_order_id,
                    'url' => route('operations.repair-orders.show', $repairOrder),
                    'age_days' => $repairOrder->closed_at !== null
                        ? max(0, (int) $repairOrder->closed_at->diffInDays(now()))
                        : 0,
                    'customer_has_email' => $hasEmail,
                    'customer_email_hint' => $hasEmail ? 'Email on file' : 'No email on file',
                    'vehicle_label' => $repairOrder->vehicle?->display_name ?? 'Vehicle',
                    'closed_label' => $repairOrder->closed_at?->timezone(config('app.timezone'))->format('M j') ?? '',
                    'reason' => $repairOrder->review_request_sent === false
                        ? ($repairOrder->review_not_requested_reason ?: 'Not requested — reason not recorded')
                        : 'Not recorded at close',
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function diagnosis(int $pressureCount, array $rows): ?string
    {
        if ($pressureCount === 0) {
            return null;
        }

        $missed = collect($rows)->firstWhere('key', 'missed_review_opportunities');
        $rate = collect($rows)->firstWhere('key', 'review_request_rate');

        if (($missed['posture'] ?? '') === 'pressure') {
            return 'Review capture is leaking at close — customers are leaving without a reputation ask.';
        }

        if (($rate['posture'] ?? '') === 'pressure') {
            return 'Review request rate is below target — log every paid close so ARK can measure selection and retention.';
        }

        return 'Market pressure detected — expand rows for evidence.';
    }

    private function eligiblePaidClosesQuery(Carbon $from, Carbon $through)
    {
        return RepairOrder::query()
            ->where('status', RepairOrderStatus::Closed)
            ->where('close_variant_key', 'paid')
            ->where('closed_at', '>=', $from)
            ->where('closed_at', '<=', $through);
    }
}
