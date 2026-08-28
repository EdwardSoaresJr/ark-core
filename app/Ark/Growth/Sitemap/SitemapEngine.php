<?php

namespace App\Ark\Growth\Sitemap;

use App\Ark\Growth\Content\ContentRegistry;
use App\Ark\Growth\Models\GrowthContent;
use App\Ark\Operations\Leads\Public\CommonProblemRegistry;
use App\Ark\Growth\PublicSurface\PublicMarketingUrl;
use Illuminate\Support\Carbon;

final class SitemapEngine
{
    public function __construct(
        private readonly ContentRegistry $contentRegistry,
    ) {}

    /**
     * @return list<array{loc: string, lastmod: string, changefreq: string, priority: string}>
     */
    public function entriesForSection(SitemapSection $section): array
    {
        return match ($section) {
            SitemapSection::Pages => $this->pageEntries(),
            SitemapSection::CommonProblems => $this->commonProblemEntries(),
            SitemapSection::Services,
            SitemapSection::Manufacturers,
            SitemapSection::Vehicles,
            SitemapSection::Blog,
            SitemapSection::Images => $this->registryEntriesForTemplate($section->value),
        };
    }

    /**
     * @return list<array{loc: string, lastmod: string}>
     */
    public function indexEntries(): array
    {
        $base = PublicMarketingUrl::baseUrl();
        $entries = [];

        foreach (SitemapSection::cases() as $section) {
            $sectionEntries = $this->entriesForSection($section);
            if ($sectionEntries === []) {
                continue;
            }

            $entries[] = [
                'loc' => $base.'/growth/sitemaps/'.$section->value.'.xml',
                'lastmod' => Carbon::now()->toAtomString(),
            ];
        }

        return $entries;
    }

    /**
     * @return list<array{loc: string, lastmod: string, changefreq: string, priority: string}>
     */
    public function allEntries(): array
    {
        $entries = [];

        foreach (SitemapSection::cases() as $section) {
            $entries = array_merge($entries, $this->entriesForSection($section));
        }

        return $entries;
    }

    /**
     * @return list<array{loc: string, lastmod: string, changefreq: string, priority: string}>
     */
    private function pageEntries(): array
    {
        $entries = [];

        foreach (config('public_seo.sitemap_paths', []) as $path => $changefreq) {
            $entries[] = $this->entry(
                path: is_string($path) ? $path : '/',
                changefreq: is_string($changefreq) ? $changefreq : 'weekly',
                priority: $path === '/' ? '1.0' : '0.8',
            );
        }

        return $entries;
    }

    /**
     * @return list<array{loc: string, lastmod: string, changefreq: string, priority: string}>
     */
    private function commonProblemEntries(): array
    {
        $entries = [
            $this->entry('/common-problems', 'monthly', '0.9'),
        ];

        foreach (CommonProblemRegistry::all() as $problem) {
            $entries[] = $this->entry(
                path: $problem['path'],
                changefreq: 'monthly',
                priority: $problem['tier'] === 1 ? '0.8' : '0.7',
            );
        }

        return $entries;
    }

    /**
     * @return list<array{loc: string, lastmod: string, changefreq: string, priority: string}>
     */
    private function registryEntriesForTemplate(string $template): array
    {
        return $this->contentRegistry
            ->publishedIndexable()
            ->filter(fn (GrowthContent $content): bool => $content->template === $template)
            ->map(fn (GrowthContent $content): array => $this->entry(
                path: $content->path,
                changefreq: config('growth.sitemap.default_changefreq', 'weekly'),
                priority: number_format($content->priority / 100, 1),
                lastmod: $content->updated_at,
            ))
            ->values()
            ->all();
    }

    private function entry(string $path, string $changefreq, string $priority, ?Carbon $lastmod = null): array
    {
        return [
            'loc' => PublicMarketingUrl::absolute($path),
            'lastmod' => ($lastmod ?? now())->toAtomString(),
            'changefreq' => $changefreq,
            'priority' => $priority,
        ];
    }
}
