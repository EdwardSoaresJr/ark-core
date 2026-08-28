<?php

namespace App\Ark\Growth\Projections;

use App\Ark\Growth\Health\GrowthHealthScorer;
use App\Ark\Growth\Integrations\Bing\BingWebmasterAdapter;
use App\Ark\Growth\Integrations\Contracts\BusinessProfileAdapter;
use App\Ark\Growth\Integrations\Google\GoogleAnalytics4Adapter;
use App\Ark\Growth\Integrations\Google\GoogleSearchConsoleAdapter;
use App\Ark\Growth\Models\GrowthAttribution;
use App\Ark\Growth\Models\GrowthContent;
use App\Ark\Growth\Models\GrowthEvent;
use App\Ark\Growth\Models\GrowthLocationMetric;
use App\Ark\Growth\Models\GrowthRedirect;
use App\Ark\Growth\Seo\Audit\SeoAuditEngine;

final class GrowthDashboardProjection
{
    public function __construct(
        private readonly GrowthHealthScorer $healthScorer,
        private readonly SeoAuditEngine $auditEngine,
        private readonly GoogleSearchConsoleAdapter $searchConsole,
        private readonly GoogleAnalytics4Adapter $analytics,
        private readonly BusinessProfileAdapter $businessProfile,
        private readonly BingWebmasterAdapter $bingWebmaster,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function resolve(): array
    {
        $health = $this->healthScorer->all();
        $audit = $this->auditEngine->summarize();

        $attributionCount = GrowthAttribution::query()->count();
        $hasAttribution = $attributionCount > 0;

        return [
            'empty_state' => [
                'visible' => ! $hasAttribution,
                'message' => 'No attribution data yet. As repair orders post, Growth will link closed work to landing pages, sources, and search terms.',
            ],
            'cards' => [
                $this->card('Website Health', 'Technical SEO, redirects, and crawl readiness', route('growth.audit'), $health['seo']->score, $health['seo']->summary),
                $this->card('Search Health', 'Queries, coverage, and position trends', route('growth.dashboard'), $health['search']->score, $health['search']->summary),
                $this->card('Analytics', 'ARK event model — adapters scaffolded', route('growth.dashboard'), $health['conversion']->score, 'GA4 adapter ready; ARK events are authoritative until sync.'),
                $this->card('Content', 'Registered public pages and publishing posture', route('growth.content.index'), $health['content']->score, $health['content']->summary),
                $this->card('Local Presence', 'Google Business Profile daily metrics', route('growth.dashboard'), $this->localPresenceScore(), $this->localPresenceSummary()),
                $this->card('Conversions', 'Visitor → lead → appointment signals', route('growth.dashboard'), $health['conversion']->score, $health['conversion']->summary),
                $this->card('Revenue Attribution', 'Closed work linked to acquisition', route('growth.revenue-explorer'), $this->attributionScore(), $this->attributionSummary()),
            ],
            'health' => array_map(fn ($score) => $score->toArray(), $health),
            'audit_counts' => $audit['counts'],
            'stats' => [
                'contents' => GrowthContent::query()->count(),
                'redirects' => GrowthRedirect::query()->where('is_active', true)->count(),
                'events' => GrowthEvent::query()->count(),
                'attributions' => GrowthAttribution::query()->count(),
                'revenue_cents' => (int) GrowthAttribution::query()->sum('revenue_cents'),
            ],
            'integrations' => [
                'search_console' => $this->searchConsole->isConfigured(),
                'analytics' => $this->analytics->isConfigured(),
                'business_profile' => $this->businessProfile->isConfigured(),
                'bing_webmaster' => $this->bingWebmaster->isConfigured(),
            ],
        ];
    }

    /**
     * @return array{title: string, hint: string, url: string, score: int, summary: string}
     */
    private function card(string $title, string $hint, string $url, int $score, string $summary): array
    {
        return [
            'title' => $title,
            'hint' => $hint,
            'url' => $url,
            'score' => $score,
            'summary' => $summary,
        ];
    }

    private function localPresenceScore(): int
    {
        if (! $this->businessProfile->isConfigured()) {
            return 40;
        }

        $metricDays = GrowthLocationMetric::query()->distinct('report_date')->count('report_date');

        return min(100, 60 + ($metricDays * 5));
    }

    private function localPresenceSummary(): string
    {
        if (! $this->businessProfile->isConfigured()) {
            return 'GBP not connected yet.';
        }

        $latestDate = GrowthLocationMetric::query()->max('report_date');
        $metricCount = GrowthLocationMetric::query()->count();

        if ($metricCount === 0) {
            return 'GBP connected — awaiting first sync.';
        }

        $callClicks = (int) GrowthLocationMetric::query()
            ->where('metric', 'CALL_CLICKS')
            ->where('report_date', $latestDate)
            ->value('value');

        return $metricCount.' metric snapshots stored. Latest day: '.$callClicks.' call clicks.';
    }

    private function attributionScore(): int
    {
        $total = GrowthAttribution::query()->count();
        $withRevenue = GrowthAttribution::query()->where('revenue_cents', '>', 0)->count();

        if ($total === 0) {
            return 0;
        }

        return min(100, (int) round(($withRevenue / $total) * 100));
    }

    private function attributionSummary(): string
    {
        $revenue = (int) GrowthAttribution::query()->sum('revenue_cents');
        $count = GrowthAttribution::query()->where('revenue_cents', '>', 0)->count();

        if ($count === 0) {
            return 'No attribution data yet.';
        }

        return '$'.number_format($revenue / 100, 0).' attributed across '.$count.' repair orders.';
    }
}
