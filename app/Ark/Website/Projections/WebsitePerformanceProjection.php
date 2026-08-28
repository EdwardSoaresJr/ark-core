<?php

namespace App\Ark\Website\Projections;

use App\Ark\Growth\Authority\GrowthPressureProjection;
use App\Ark\Growth\Integrations\SearchMetricsAggregator;
use App\Ark\Growth\Models\GrowthLandingPageMetric;
use App\Ark\Growth\Models\GrowthOpportunity;
use App\Ark\Growth\Models\GrowthSearchQuery;
use App\Ark\Growth\Opportunities\OpportunityQueueRepository;
use App\Ark\Operations\Leads\Lead;
use App\Ark\Operations\Leads\LeadSource;
use App\Ark\Operations\Leads\LeadState;
use App\Ark\Operations\Leads\Public\CommonProblemRegistry;
use App\Ark\Operations\Leads\Public\PublicLeadFunnelSummary;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

final class WebsitePerformanceProjection
{
    public function __construct(
        private readonly PublicLeadFunnelSummary $funnel,
        private readonly SearchMetricsAggregator $searchMetrics,
        private readonly OpportunityQueueRepository $opportunities,
        private readonly WebsiteHealthProjection $health,
        private readonly WebsitePublishQueueProjection $publishQueue,
        private readonly GrowthPressureProjection $marketPressure,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function resolve(): array
    {
        $weekStart = now()->startOfWeek();
        $weekEnd = now()->endOfDay();
        $funnel = $this->funnel->forPeriod($weekStart, $weekEnd);

        return [
            'period_label' => 'This week',
            'leads' => [
                'value' => $funnel['leads_created'],
                'subtitle' => ($funnel['lead_submitted'] ?? 0).' form submissions',
                'growth_url' => route('growth.sessions.index'),
            ],
            'top_landing_page' => $this->topLandingPage(),
            'top_opportunity' => $this->topOpportunity(),
            'organic_trend' => $this->organicTrend(),
            'recent_submissions' => $this->recentSubmissions(),
            'spam_observation' => $this->spamObservation(),
            'health' => $this->health->resolve(),
            'publish_queue' => $this->publishQueue->resolve(),
            'market_pressure' => $this->marketPressure->resolve(),
            'growth_home_url' => route('growth.opportunities.index'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function topLandingPage(): array
    {
        $this->opportunities->syncDiscovered();

        $landing = $this->searchMetrics->aggregatedLandingPages()->first();

        if ($landing !== null && filled($landing->path)) {
            return [
                'available' => true,
                'value' => $this->labelForPath((string) $landing->path),
                'path' => (string) $landing->path,
                'growth_url' => route('growth.content.index'),
            ];
        }

        return [
            'available' => false,
            'value' => 'Homepage',
            'path' => '/',
            'growth_url' => route('growth.sessions.index'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function topOpportunity(): array
    {
        $opportunity = $this->opportunities->topOpportunities(1)[0] ?? null;

        if ($opportunity instanceof GrowthOpportunity) {
            return [
                'available' => true,
                'value' => $opportunity->title,
                'growth_url' => route('growth.opportunities.build', $opportunity),
            ];
        }

        return [
            'available' => false,
            'value' => 'No opportunities yet',
            'growth_url' => route('growth.opportunities.index'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function organicTrend(): array
    {
        $hasData = GrowthSearchQuery::query()->exists() || GrowthLandingPageMetric::query()->exists();

        if (! $hasData) {
            return [
                'available' => false,
                'value' => 'Not available yet',
                'growth_url' => route('growth.opportunities.index'),
            ];
        }

        $end = Carbon::now()->endOfDay();
        $thisStart = $end->copy()->subDays(6)->startOfDay();
        $previousStart = $end->copy()->subDays(13)->startOfDay();
        $previousEnd = $end->copy()->subDays(7)->endOfDay();

        $current = (int) GrowthSearchQuery::query()
            ->whereDate('report_date', '>=', $thisStart->toDateString())
            ->whereDate('report_date', '<=', $end->toDateString())
            ->sum('clicks');

        $previous = (int) GrowthSearchQuery::query()
            ->whereDate('report_date', '>=', $previousStart->toDateString())
            ->whereDate('report_date', '<=', $previousEnd->toDateString())
            ->sum('clicks');

        if ($previous === 0 && $current === 0) {
            return [
                'available' => false,
                'value' => 'Awaiting Search Console sync',
                'growth_url' => route('growth.opportunities.index'),
            ];
        }

        if ($previous === 0) {
            return [
                'available' => true,
                'value' => '↑ New traffic',
                'growth_url' => route('growth.revenue-explorer'),
            ];
        }

        $change = (int) round((($current - $previous) / $previous) * 100);

        return [
            'available' => true,
            'value' => ($change >= 0 ? '↑ ' : '↓ ').abs($change).'% organic clicks',
            'growth_url' => route('growth.revenue-explorer'),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentSubmissions(): array
    {
        return Lead::query()
            ->notSpam()
            ->where('source', LeadSource::Website)
            ->where('created_at', '>=', now()->startOfWeek())
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (Lead $lead): array => [
                'id' => $lead->id,
                'label' => Str::limit($lead->concern ?: 'Website lead', 72),
                'created_label' => $lead->created_at?->timezone(config('app.timezone'))->format('M j, g:i A') ?? '',
                'url' => \App\Ark\Operations\Communications\CommunicationsNeedsYou::url(),
            ])
            ->all();
    }

    /**
     * Owner observation only — auto-spam does not create Conversation / Inbox rows.
     *
     * @return list<array<string, mixed>>
     */
    private function spamObservation(): array
    {
        return Lead::query()
            ->where('state', LeadState::Spam)
            ->where('source', LeadSource::Website)
            ->latest()
            ->limit(20)
            ->get()
            ->map(function (Lead $lead): array {
                $signals = is_array($lead->spam_signals) ? $lead->spam_signals : [];

                return [
                    'id' => $lead->id,
                    'label' => Str::limit($lead->concern ?: 'Spam lead', 72),
                    'created_label' => $lead->created_at?->timezone(config('app.timezone'))->format('M j, g:i A') ?? '',
                    'ingress_ip' => $lead->ingress_ip,
                    'ingress_referrer' => $lead->ingress_referrer,
                    'ingress_user_agent' => $lead->ingress_user_agent,
                    'signals_label' => $signals !== [] ? implode(', ', $signals) : null,
                ];
            })
            ->all();
    }

    private function labelForPath(string $path): string
    {
        if ($path === '/' || $path === '') {
            return 'Homepage';
        }

        if (str_starts_with($path, '/common-problems/')) {
            $slug = trim(str_replace('/common-problems/', '', $path), '/');
            $problem = CommonProblemRegistry::find($slug);

            return $problem['title'] ?? Str::headline(str_replace('-', ' ', $slug));
        }

        return Str::headline(trim(str_replace(['/', '-'], [' ', ' '], $path), ' '));
    }
}
