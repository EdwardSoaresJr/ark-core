<?php

namespace App\Ark\Growth\Seo\Audit\Analyzers;

use App\Ark\Growth\Models\GrowthContent;
use App\Ark\Growth\Seo\Audit\RuntimeSeoAuditFinding;
use App\Ark\Growth\Seo\Audit\SeoAuditAnalyzer;
use App\Ark\Growth\Seo\Audit\SeoAuditAuthoritySource;

final class MultipleH1Analyzer implements SeoAuditAnalyzer
{
    public function name(): string
    {
        return 'multiple_h1';
    }

    public function analyze(): array
    {
        $findings = [];

        GrowthContent::query()->each(function (GrowthContent $content) use (&$findings): void {
            $h1Count = (int) ($content->metadata['h1_count'] ?? 0);

            if ($h1Count > 1) {
                $findings[] = RuntimeSeoAuditFinding::failure(
                    analyzer: $this->name(),
                    severity: 'warning',
                    category: 'metadata',
                    message: 'Page has more than one H1 heading.',
                    recommendation: 'Use one H1 for the primary topic; demote additional headings to H2 or lower.',
                    authoritySource: SeoAuditAuthoritySource::Crawl,
                    path: $content->path,
                    evidence: 'h1_count='.$h1Count.' from rendered HTML crawl',
                );
            }
        });

        return $findings;
    }
}
