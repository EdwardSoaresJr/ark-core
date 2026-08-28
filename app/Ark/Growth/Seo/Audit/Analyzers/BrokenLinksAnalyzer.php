<?php

namespace App\Ark\Growth\Seo\Audit\Analyzers;

use App\Ark\Growth\Models\GrowthContent;
use App\Ark\Growth\Seo\Audit\RuntimeSeoAuditFinding;
use App\Ark\Growth\Seo\Audit\SeoAuditAnalyzer;
use App\Ark\Growth\Seo\Audit\SeoAuditAuthoritySource;

final class BrokenLinksAnalyzer implements SeoAuditAnalyzer
{
    public function name(): string
    {
        return 'broken_links';
    }

    public function analyze(): array
    {
        $findings = [];

        GrowthContent::query()->each(function (GrowthContent $content) use (&$findings): void {
            $brokenLinks = (int) ($content->metadata['broken_link_count'] ?? 0);

            if ($brokenLinks > 0) {
                $findings[] = RuntimeSeoAuditFinding::failure(
                    analyzer: $this->name(),
                    severity: 'critical',
                    category: 'links',
                    message: 'Page contains broken outbound or internal links.',
                    recommendation: 'Fix or remove dead links so crawlers and customers do not hit 404s from this page.',
                    authoritySource: SeoAuditAuthoritySource::Crawl,
                    path: $content->path,
                    evidence: 'broken_link_count='.$brokenLinks.' from nightly crawl metadata',
                );
            }
        });

        return $findings;
    }
}
