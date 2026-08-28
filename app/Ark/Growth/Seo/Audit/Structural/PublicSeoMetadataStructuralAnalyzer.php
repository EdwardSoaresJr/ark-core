<?php

namespace App\Ark\Growth\Seo\Audit\Structural;

use App\Ark\Growth\Seo\Audit\SeoAuditAnalyzer;
use App\Ark\Growth\Seo\Audit\SeoAuditAuthoritySource;
use App\Ark\Growth\Seo\Audit\SeoAuditChannel;
use App\Ark\Growth\Seo\Audit\SeoAuditFinding;
use App\Ark\Growth\Seo\Audit\SeoAuditVerification;
use App\Ark\Operations\Leads\Public\CommonProblemRegistry;

final class PublicSeoMetadataStructuralAnalyzer implements SeoAuditAnalyzer
{
    public function name(): string
    {
        return 'public_seo_metadata';
    }

    public function analyze(): array
    {
        $verifiedAt = SeoAuditVerification::atRequestTime();
        $findings = [];

        $home = config('public_seo.home', []);
        if (trim((string) ($home['title_suffix'] ?? '')) === '') {
            $findings[] = $this->failure('Homepage title suffix missing in public_seo.home', '/', $verifiedAt);
        }

        if (trim((string) ($home['description'] ?? '')) === '') {
            $findings[] = $this->failure('Homepage meta description missing in public_seo.home', '/', $verifiedAt, 'description');
        }

        $titles = [];

        foreach (CommonProblemRegistry::all() as $problem) {
            $slug = (string) ($problem['slug'] ?? '');
            $path = (string) ($problem['path'] ?? '/common-problems/'.$slug);
            $title = trim((string) ($problem['title'] ?? ''));
            $description = trim((string) ($problem['meta_description'] ?? ''));

            if ($title === '') {
                $findings[] = $this->failure('Problem authority missing title', $path, $verifiedAt);
            } else {
                $titles[$title][] = $path;
            }

            if ($description === '') {
                $findings[] = $this->failure('Problem authority missing meta description', $path, $verifiedAt, 'description');
            }
        }

        foreach ($titles as $title => $paths) {
            if (count($paths) < 2) {
                continue;
            }

            $findings[] = new SeoAuditFinding(
                id: 'duplicate_title_'.md5($title),
                title: 'Duplicate title',
                severity: 'medium',
                category: 'metadata',
                message: 'Multiple problem authority pages share the same title.',
                recommendation: 'Give each CommonProblemRegistry entry a unique, descriptive title.',
                url: $paths[0],
                evidence: 'Title "'.$title.'" used on: '.implode(', ', $paths),
                authoritySource: SeoAuditAuthoritySource::Configuration,
                channel: SeoAuditChannel::Structural,
                verifiedAt: $verifiedAt,
            );
        }

        if ($findings === []) {
            $findings[] = new SeoAuditFinding(
                id: 'public_seo_metadata',
                title: 'Public SEO metadata',
                severity: 'info',
                category: 'metadata',
                message: 'Homepage and every problem authority page have unique titles and meta descriptions.',
                recommendation: 'No action required.',
                evidence: 'public_seo.home + CommonProblemRegistry. '.SeoAuditVerification::deploymentLabel().'.',
                authoritySource: SeoAuditAuthoritySource::Configuration,
                channel: SeoAuditChannel::Structural,
                passed: true,
                verifiedAt: $verifiedAt,
            );
        }

        return $findings;
    }

    private function failure(string $message, string $path, string $verifiedAt, string $field = 'title'): SeoAuditFinding
    {
        return new SeoAuditFinding(
            id: 'missing_'.$field.'_'.md5($path),
            title: $field === 'description' ? 'Missing meta description' : 'Missing title',
            severity: 'high',
            category: 'metadata',
            message: $message,
            recommendation: $field === 'description'
                ? 'Add meta_description to the problem authority entry or public_seo config.'
                : 'Add a unique title to the problem authority entry or public_seo config.',
            url: url($path),
            evidence: 'Read from CommonProblemRegistry / public_seo — not GrowthContent registry.',
            authoritySource: SeoAuditAuthoritySource::Configuration,
            channel: SeoAuditChannel::Structural,
            verifiedAt: $verifiedAt,
        );
    }
}
