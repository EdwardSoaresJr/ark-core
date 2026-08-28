<?php

namespace App\Ark\Growth\Projections;

use App\Ark\Growth\Events\GrowthEventType;
use App\Ark\Growth\Models\GrowthAttribution;
use App\Ark\Growth\Models\GrowthContent;
use App\Ark\Growth\Models\GrowthEvent;
use App\Ark\Growth\Models\GrowthLandingPageMetric;
use App\Ark\Growth\Models\GrowthSearchQuery;
use Illuminate\Support\Facades\DB;

final class RevenueExplorerProjection
{
    /**
     * @return array<string, mixed>
     */
    public function resolve(string $queryType, int $revenueThresholdCents, int $limit = 50): array
    {
        return match ($queryType) {
            'high_revenue_pages' => $this->highRevenuePages($revenueThresholdCents, $limit),
            'search_terms_by_ro' => $this->searchTermsByAverageRo($limit),
            'service_conversion' => $this->servicePageConversion($limit),
            'traffic_no_appointments' => $this->trafficWithoutAppointments($limit),
            'content_demand' => $this->contentDemandSignals($limit),
            default => $this->highRevenuePages($revenueThresholdCents, $limit),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function highRevenuePages(int $thresholdCents, int $limit): array
    {
        $rows = GrowthContent::query()
            ->where('revenue_cents', '>=', $thresholdCents)
            ->orderByDesc('revenue_cents')
            ->limit($limit)
            ->get()
            ->map(fn (GrowthContent $content): array => [
                'path' => $content->path,
                'title' => $content->title,
                'template' => $content->template,
                'revenue' => '$'.number_format($content->revenue_cents / 100, 0),
                'revenue_cents' => $content->revenue_cents,
                'search_clicks' => $content->search_clicks,
                'impressions' => $content->search_impressions,
            ]);

        return [
            'query_type' => 'high_revenue_pages',
            'question' => 'Which pages produced more than $'.number_format($thresholdCents / 100, 0).' in revenue?',
            'rows' => $rows->all(),
            'empty_hint' => $rows->isEmpty()
                ? 'No pages meet this threshold yet. Attribution populates when closed repair orders dispatch the Growth contract event.'
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function searchTermsByAverageRo(int $limit): array
    {
        $rows = GrowthSearchQuery::query()
            ->where('repair_order_count', '>', 0)
            ->select([
                'query',
                DB::raw('SUM(revenue_cents) as total_revenue_cents'),
                DB::raw('SUM(repair_order_count) as total_ros'),
                DB::raw('ROUND(SUM(revenue_cents) / SUM(repair_order_count)) as avg_revenue_cents'),
            ])
            ->groupBy('query')
            ->orderByDesc('avg_revenue_cents')
            ->limit($limit)
            ->get()
            ->map(fn ($row): array => [
                'query' => $row->query,
                'repair_orders' => (int) $row->total_ros,
                'avg_revenue' => '$'.number_format(((int) $row->avg_revenue_cents) / 100, 0),
                'total_revenue' => '$'.number_format(((int) $row->total_revenue_cents) / 100, 0),
            ]);

        return [
            'query_type' => 'search_terms_by_ro',
            'question' => 'Which search terms generate the highest average repair order?',
            'rows' => $rows->all(),
            'empty_hint' => $rows->isEmpty()
                ? 'Import Search Console data or link attributions with search_query to answer this.'
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function servicePageConversion(int $limit): array
    {
        $rows = GrowthContent::query()
            ->where('template', 'services')
            ->orderByDesc('revenue_cents')
            ->limit($limit)
            ->get()
            ->map(function (GrowthContent $content): array {
                $appointments = GrowthEvent::query()
                    ->where('growth_content_id', $content->id)
                    ->where('type', GrowthEventType::AppointmentScheduled)
                    ->count();
                $views = GrowthEvent::query()
                    ->where('growth_content_id', $content->id)
                    ->where('type', GrowthEventType::PageViewed)
                    ->count();
                $rate = $views > 0 ? round(($appointments / $views) * 100, 1) : 0;

                return [
                    'path' => $content->path,
                    'title' => $content->title,
                    'views' => $views,
                    'appointments' => $appointments,
                    'conversion_rate' => $rate.'%',
                    'revenue' => '$'.number_format($content->revenue_cents / 100, 0),
                ];
            })
            ->sortByDesc(fn (array $row): float => (float) str_replace('%', '', $row['conversion_rate']))
            ->values();

        return [
            'query_type' => 'service_conversion',
            'question' => 'Which service pages convert the best?',
            'rows' => $rows->all(),
            'empty_hint' => $rows->isEmpty()
                ? 'Register service pages in the content registry with template=services.'
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function trafficWithoutAppointments(int $limit): array
    {
        $rows = GrowthLandingPageMetric::query()
            ->where('page_views', '>=', 100)
            ->where('appointments', '<=', 2)
            ->orderByDesc('page_views')
            ->limit($limit)
            ->get()
            ->map(fn (GrowthLandingPageMetric $metric): array => [
                'path' => $metric->path,
                'page_views' => $metric->page_views,
                'appointments' => $metric->appointments,
                'clicks' => $metric->clicks,
                'impressions' => $metric->impressions,
                'revenue' => '$'.number_format($metric->revenue_cents / 100, 0),
            ]);

        if ($rows->isEmpty()) {
            $rows = GrowthContent::query()
                ->where('search_impressions', '>=', 50)
                ->where('revenue_cents', '=', 0)
                ->orderByDesc('search_impressions')
                ->limit($limit)
                ->get()
                ->map(fn (GrowthContent $content): array => [
                    'path' => $content->path,
                    'page_views' => '—',
                    'appointments' => 0,
                    'clicks' => $content->search_clicks,
                    'impressions' => $content->search_impressions,
                    'revenue' => '$0',
                ]);
        }

        return [
            'query_type' => 'traffic_no_appointments',
            'question' => 'Which pages have lots of traffic but almost no appointments?',
            'rows' => $rows->all(),
            'empty_hint' => $rows->isEmpty()
                ? 'Landing page metrics populate from Growth events and Search Console imports.'
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function contentDemandSignals(int $limit): array
    {
        $attributedQueries = GrowthAttribution::query()
            ->whereNotNull('search_query')
            ->select('search_query', DB::raw('COUNT(*) as ro_count'), DB::raw('SUM(revenue_cents) as revenue_cents'))
            ->groupBy('search_query')
            ->orderByDesc('revenue_cents')
            ->limit($limit)
            ->get();

        $rows = $attributedQueries->map(fn ($row): array => [
            'topic' => $row->search_query,
            'repair_orders' => (int) $row->ro_count,
            'revenue' => '$'.number_format(((int) $row->revenue_cents) / 100, 0),
            'recommendation' => 'Create or strengthen content targeting this demand — revenue already proven.',
        ]);

        return [
            'query_type' => 'content_demand',
            'question' => 'What content should we create next based on actual shop demand?',
            'rows' => $rows->all(),
            'empty_hint' => $rows->isEmpty()
                ? 'Demand signals appear when attributions include search_query or when common-problem leads cluster.'
                : null,
        ];
    }

    /**
     * @return list<array{key: string, label: string, question: string}>
     */
    public function queryCatalog(): array
    {
        return [
            ['key' => 'high_revenue_pages', 'label' => 'High-revenue pages', 'question' => 'Pages that produced significant closed work revenue'],
            ['key' => 'search_terms_by_ro', 'label' => 'Search terms by avg RO', 'question' => 'Queries with the highest average repair order value'],
            ['key' => 'service_conversion', 'label' => 'Service page conversion', 'question' => 'Which service pages turn views into appointments'],
            ['key' => 'traffic_no_appointments', 'label' => 'Traffic without appointments', 'question' => 'High visibility pages that fail to convert'],
            ['key' => 'content_demand', 'label' => 'Content demand', 'question' => 'Proven demand from attributed search and leads'],
        ];
    }
}
