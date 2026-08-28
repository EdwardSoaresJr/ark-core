<?php

namespace App\Ark\Growth\Seo\Audit\Analyzers;

use App\Ark\Growth\Models\GrowthContent;
use App\Ark\Growth\Seo\Audit\RuntimeSeoAuditFinding;
use App\Ark\Growth\Seo\Audit\SeoAuditAnalyzer;
use App\Ark\Growth\Seo\Audit\SeoAuditAuthoritySource;

final class CanonicalIssueAnalyzer implements SeoAuditAnalyzer
{
    public function name(): string
    {
        return 'canonical_issue';
    }

    public function analyze(): array
    {
        $findings = [];

        GrowthContent::query()->each(function (GrowthContent $content) use (&$findings): void {
            if ((bool) ($content->metadata['canonical_mismatch'] ?? false)) {
                $findings[] = RuntimeSeoAuditFinding::failure(
                    analyzer: $this->name(),
                    severity: 'critical',
                    category: 'metadata',
                    message: 'Canonical URL does not match the preferred page URL.',
                    recommendation: 'Align the canonical tag with the indexable URL or redirect duplicates to the canonical path.',
                    authoritySource: SeoAuditAuthoritySource::Crawl,
                    path: $content->path,
                    evidence: 'canonical='.($content->metadata['canonical_url'] ?? '').' from rendered HTML crawl',
                );
            }

            if ((bool) ($content->metadata['missing_canonical'] ?? false)) {
                $findings[] = RuntimeSeoAuditFinding::failure(
                    analyzer: $this->name(),
                    severity: 'warning',
                    category: 'metadata',
                    message: 'Indexable page is missing a canonical URL.',
                    recommendation: 'Set an explicit canonical URL so search engines consolidate signals on one URL.',
                    authoritySource: SeoAuditAuthoritySource::Crawl,
                    path: $content->path,
                    evidence: 'missing_canonical=true from rendered HTML crawl',
                );
            }
        });

        return $findings;
    }
}
