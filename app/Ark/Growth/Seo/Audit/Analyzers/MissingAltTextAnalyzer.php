<?php

namespace App\Ark\Growth\Seo\Audit\Analyzers;

use App\Ark\Growth\Models\GrowthContent;
use App\Ark\Growth\Seo\Audit\RuntimeSeoAuditFinding;
use App\Ark\Growth\Seo\Audit\SeoAuditAnalyzer;
use App\Ark\Growth\Seo\Audit\SeoAuditAuthoritySource;

final class MissingAltTextAnalyzer implements SeoAuditAnalyzer
{
    public function name(): string
    {
        return 'missing_alt_text';
    }

    public function analyze(): array
    {
        $findings = [];

        GrowthContent::query()->each(function (GrowthContent $content) use (&$findings): void {
            $missingAlt = (int) ($content->metadata['images_without_alt'] ?? 0);

            if ($missingAlt > 0) {
                $findings[] = RuntimeSeoAuditFinding::failure(
                    analyzer: $this->name(),
                    severity: 'warning',
                    category: 'accessibility',
                    message: 'Page images are missing alt text.',
                    recommendation: 'Add descriptive alt attributes so search engines and screen readers understand each image.',
                    authoritySource: SeoAuditAuthoritySource::Crawl,
                    path: $content->path,
                    evidence: 'images_without_alt='.$missingAlt.' from nightly crawl metadata',
                );
            }
        });

        return $findings;
    }
}
