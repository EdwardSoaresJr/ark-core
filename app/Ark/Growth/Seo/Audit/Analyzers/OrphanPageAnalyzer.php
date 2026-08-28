<?php

namespace App\Ark\Growth\Seo\Audit\Analyzers;

use App\Ark\Growth\Models\GrowthContent;
use App\Ark\Growth\Seo\Audit\RuntimeSeoAuditFinding;
use App\Ark\Growth\Seo\Audit\SeoAuditAnalyzer;
use App\Ark\Growth\Seo\Audit\SeoAuditAuthoritySource;

final class OrphanPageAnalyzer implements SeoAuditAnalyzer
{
    public function name(): string
    {
        return 'orphan_page';
    }

    public function analyze(): array
    {
        $findings = [];

        GrowthContent::query()
            ->where('indexable', true)
            ->each(function (GrowthContent $content) use (&$findings): void {
                $inboundLinks = (int) ($content->metadata['inbound_link_count'] ?? 0);

                if ($inboundLinks === 0 && $content->path !== '/') {
                    $findings[] = RuntimeSeoAuditFinding::failure(
                        analyzer: $this->name(),
                        severity: 'info',
                        category: 'navigation',
                        message: 'No internal links point to this page.',
                        recommendation: 'Link from related service pages, common problems, or the homepage so crawlers and customers can reach it.',
                        authoritySource: SeoAuditAuthoritySource::Crawl,
                        path: $content->path,
                        evidence: 'inbound_link_count=0 from crawl link graph',
                    );
                }
            });

        return $findings;
    }
}
