<?php

namespace App\Ark\Growth\Integrations;

use App\Ark\Growth\Content\GeneratedCommonProblemRepository;
use App\Ark\Growth\Integrations\Google\GoogleIndexingNotifier;
use App\Ark\Growth\Integrations\Google\GoogleSearchConsoleAdapter;
use App\Ark\Growth\Integrations\IndexNow\IndexNowNotifier;
use App\Ark\Growth\PublicSurface\PublicMarketingUrl;
use App\Ark\Operations\Leads\Public\CommonProblemRegistry;

/**
 * Pings search engines after nightly maintenance or new page publishes.
 */
final class SearchEngineNotificationService
{
    public function __construct(
        private readonly IndexNowNotifier $indexNow,
        private readonly GoogleSearchConsoleAdapter $searchConsole,
        private readonly GoogleIndexingNotifier $googleIndexing,
        private readonly GeneratedCommonProblemRepository $generatedProblems,
    ) {}

    /**
     * @return list<string>
     */
    public function commonProblemPaths(): array
    {
        return collect(CommonProblemRegistry::all())
            ->pluck('path')
            ->filter(fn (string $path): bool => $path !== '')
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function notifyCommonProblems(): array
    {
        return $this->notifyPaths($this->commonProblemPaths());
    }

    /**
     * @return array<string, mixed>
     */
    public function notifyAfterMaintenance(): array
    {
        if (! config('growth.seo_automation.notify_search_engines', true)) {
            return ['skipped' => true, 'reason' => 'Search engine notifications disabled.'];
        }

        $base = PublicMarketingUrl::baseUrl();
        $sitemapUrl = $base.'/sitemap.xml';
        $urls = $this->urlsToNotify();

        $results = [
            'sitemap_url' => $sitemapUrl,
            'url_count' => count($urls),
        ];

        if ($this->indexNow->isConfigured()) {
            $indexNowUrls = array_merge([$sitemapUrl], $urls);
            $results['indexnow'] = $this->indexNow->submitUrls($indexNowUrls);
        } else {
            $this->indexNow->ensureKey();
            $results['indexnow'] = [
                'submitted' => false,
                'message' => 'IndexNow key generated — key file is live; submission resumes on next run.',
            ];
        }

        if ($this->searchConsole->isConfigured()) {
            $results['google_sitemap'] = $this->searchConsole->submitSitemap($sitemapUrl);
        }

        if ($this->googleIndexing->isConfigured() && $urls !== []) {
            $results['google_indexing'] = $this->googleIndexing->notifyUpdated($urls);
        }

        return $results;
    }

    /**
     * @param  list<string>  $paths
     * @return array<string, mixed>
     */
    public function notifyPaths(array $paths): array
    {
        $base = PublicMarketingUrl::baseUrl();
        $urls = collect($paths)
            ->map(static fn (string $path): string => $base.'/'.trim($path, '/'))
            ->values()
            ->all();

        $results = [];

        if ($this->indexNow->isConfigured()) {
            $results['indexnow'] = $this->indexNow->submitUrls($urls);
        }

        if ($this->googleIndexing->isConfigured()) {
            $results['google_indexing'] = $this->googleIndexing->notifyUpdated($urls);
        }

        if ($this->searchConsole->isConfigured()) {
            $results['google_sitemap'] = $this->searchConsole->submitSitemap($base.'/sitemap.xml');
        }

        return $results;
    }

    /**
     * @return list<string>
     */
    private function urlsToNotify(): array
    {
        $base = PublicMarketingUrl::baseUrl();

        return $this->generatedProblems
            ->publishedSince(now()->subDays(2))
            ->map(static fn ($row): string => $base.'/common-problems/'.$row->slug)
            ->values()
            ->all();
    }
}
