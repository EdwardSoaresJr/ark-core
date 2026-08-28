<?php

namespace App\Ark\Growth\Health;

use App\Ark\Growth\Events\GrowthEventType;
use App\Ark\Growth\Models\GrowthAttribution;
use App\Ark\Growth\Models\GrowthContent;
use App\Ark\Growth\Models\GrowthEvent;
use App\Ark\Growth\Models\GrowthSearchQuery;
use App\Ark\Growth\Seo\Audit\SeoAuditEngine;

final class GrowthHealthScorer
{
    public function __construct(
        private readonly SeoAuditEngine $auditEngine,
    ) {}

    public function seo(): ExplainableHealthScore
    {
        $audit = $this->auditEngine->summarize();
        $critical = (int) ($audit['counts']['critical'] ?? 0);
        $warning = (int) ($audit['counts']['warning'] ?? 0);
        $deduction = min(100, ($critical * 15) + ($warning * 5));
        $score = max(0, 100 - $deduction);

        return new ExplainableHealthScore(
            domain: 'seo',
            score: $score,
            summary: $critical === 0 && $warning === 0
                ? 'No critical SEO audit issues detected in the content registry.'
                : "{$critical} critical and {$warning} warning SEO findings need attention.",
            factors: [
                ['label' => 'Critical findings', 'impact' => '-'.($critical * 15), 'detail' => 'Missing titles, broken canonicals, indexability conflicts'],
                ['label' => 'Warnings', 'impact' => '-'.($warning * 5), 'detail' => 'Duplicate titles, thin content, missing descriptions'],
            ],
        );
    }

    public function content(): ExplainableHealthScore
    {
        $total = GrowthContent::query()->count();
        $published = GrowthContent::query()->whereNotNull('published_at')->count();
        $indexable = GrowthContent::query()->where('indexable', true)->count();
        $withRevenue = GrowthContent::query()->where('revenue_cents', '>', 0)->count();

        $publishRatio = $total > 0 ? (int) round(($published / $total) * 100) : 0;
        $revenueRatio = $published > 0 ? (int) round(($withRevenue / $published) * 100) : 0;
        $score = (int) round(($publishRatio * 0.4) + ($revenueRatio * 0.6));

        return new ExplainableHealthScore(
            domain: 'content',
            score: min(100, $score),
            summary: "{$published} of {$total} registered pages published; {$withRevenue} pages have attributed revenue.",
            factors: [
                ['label' => 'Published ratio', 'impact' => (string) $publishRatio, 'detail' => "{$published}/{$total} pages live"],
                ['label' => 'Revenue-producing pages', 'impact' => (string) $revenueRatio, 'detail' => "{$withRevenue} pages with closed RO revenue"],
                ['label' => 'Indexable pages', 'impact' => (string) $indexable, 'detail' => "{$indexable} pages set to index"],
            ],
        );
    }

    public function search(): ExplainableHealthScore
    {
        $queries = GrowthSearchQuery::query()->count();
        $withRevenue = GrowthSearchQuery::query()->where('revenue_cents', '>', 0)->count();
        $avgPosition = GrowthSearchQuery::query()->avg('position');

        $score = $queries === 0
            ? 50
            : min(100, (int) round(50 + ($withRevenue / max(1, $queries)) * 50));

        return new ExplainableHealthScore(
            domain: 'search',
            score: $score,
            summary: $queries === 0
                ? 'Search Console data not synced yet — schema ready for import.'
                : "{$withRevenue} of {$queries} tracked queries have attributed revenue.",
            factors: [
                ['label' => 'Tracked queries', 'impact' => (string) $queries, 'detail' => 'Internal Search Console model rows'],
                ['label' => 'Revenue-linked queries', 'impact' => (string) $withRevenue, 'detail' => 'Queries tied to closed work'],
                ['label' => 'Average position', 'impact' => $avgPosition !== null ? number_format((float) $avgPosition, 1) : '—', 'detail' => 'From last import period'],
            ],
        );
    }

    public function conversion(): ExplainableHealthScore
    {
        $pageViews = GrowthEvent::query()->where('type', GrowthEventType::PageViewed)->count();
        $appointments = GrowthEvent::query()->where('type', GrowthEventType::AppointmentScheduled)->count();
        $revenueEvents = GrowthAttribution::query()->where('revenue_cents', '>', 0)->count();

        $rate = $pageViews > 0 ? round(($appointments / $pageViews) * 100, 2) : 0;
        $score = min(100, (int) round(($rate * 10) + min(40, $revenueEvents * 2)));

        return new ExplainableHealthScore(
            domain: 'conversion',
            score: $score,
            summary: $pageViews === 0
                ? 'Event collection started — conversion health will improve as ARK Growth events accumulate.'
                : "Appointment rate {$rate}% from {$pageViews} page views; {$revenueEvents} revenue attributions recorded.",
            factors: [
                ['label' => 'Page views', 'impact' => (string) $pageViews, 'detail' => 'Growth event model'],
                ['label' => 'Appointments', 'impact' => (string) $appointments, 'detail' => 'Scheduled from public surfaces'],
                ['label' => 'Revenue attributions', 'impact' => (string) $revenueEvents, 'detail' => 'Closed ROs linked to acquisition'],
            ],
        );
    }

    /**
     * @return array<string, ExplainableHealthScore>
     */
    public function all(): array
    {
        return [
            'seo' => $this->seo(),
            'content' => $this->content(),
            'search' => $this->search(),
            'conversion' => $this->conversion(),
        ];
    }
}
