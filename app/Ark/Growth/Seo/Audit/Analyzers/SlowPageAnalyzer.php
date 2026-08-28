<?php

namespace App\Ark\Growth\Seo\Audit\Analyzers;

use App\Ark\Growth\Models\GrowthContent;
use App\Ark\Growth\Seo\Audit\RuntimeSeoAuditFinding;
use App\Ark\Growth\Seo\Audit\SeoAuditAnalyzer;
use App\Ark\Growth\Seo\Audit\SeoAuditAuthoritySource;

final class SlowPageAnalyzer implements SeoAuditAnalyzer
{
    private const SLOW_LOAD_MS = 3000;

    public function name(): string
    {
        return 'slow_page';
    }

    public function analyze(): array
    {
        $findings = [];

        GrowthContent::query()->each(function (GrowthContent $content) use (&$findings): void {
            $loadMs = (int) ($content->metadata['load_ms'] ?? 0);

            if ($loadMs <= self::SLOW_LOAD_MS) {
                return;
            }

            $findings[] = RuntimeSeoAuditFinding::failure(
                analyzer: $this->name(),
                severity: 'warning',
                category: 'performance',
                message: 'Page load time exceeds the growth performance threshold.',
                recommendation: 'Reduce render-blocking assets, compress images, and defer non-critical scripts until LCP improves.',
                authoritySource: SeoAuditAuthoritySource::Crawl,
                path: $content->path,
                evidence: 'load_ms='.$loadMs.' from nightly crawl metadata',
            );
        });

        return $findings;
    }
}
