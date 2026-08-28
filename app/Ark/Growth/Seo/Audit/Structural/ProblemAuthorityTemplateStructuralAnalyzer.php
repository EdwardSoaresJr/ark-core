<?php

namespace App\Ark\Growth\Seo\Audit\Structural;

use App\Ark\Growth\Seo\Audit\SeoAuditAnalyzer;
use App\Ark\Growth\Seo\Audit\SeoAuditAuthoritySource;
use App\Ark\Growth\Seo\Audit\SeoAuditChannel;
use App\Ark\Growth\Seo\Audit\SeoAuditFinding;
use App\Ark\Growth\Seo\Audit\SeoAuditVerification;

final class ProblemAuthorityTemplateStructuralAnalyzer implements SeoAuditAnalyzer
{
    public function name(): string
    {
        return 'problem_authority_template';
    }

    public function analyze(): array
    {
        $verifiedAt = SeoAuditVerification::atRequestTime();
        $deployLabel = SeoAuditVerification::deploymentLabel();

        $showPath = base_path('resources/views/public/common-problems/show.blade.php');
        $authorityPartial = base_path('resources/views/partials/public/common-problem-authority.blade.php');
        $shopPartial = base_path('resources/views/partials/public/common-problem-shop-experience.blade.php');

        $missing = [];

        if (! is_file($showPath)) {
            $missing[] = 'common-problems/show.blade.php';
        } else {
            $show = (string) file_get_contents($showPath);

            if (! str_contains($show, 'common-problem-authority')) {
                $missing[] = 'show.blade.php must include common-problem-authority partial';
            }
        }

        if (! is_file($authorityPartial)) {
            $missing[] = 'common-problem-authority.blade.php partial';
        } else {
            $authority = (string) file_get_contents($authorityPartial);
            $sections = [
                'symptoms',
                'common_causes',
                'diagnostic_process',
                'typical_repairs',
                'faq',
            ];

            foreach ($sections as $section) {
                if (! str_contains($authority, $section)) {
                    $missing[] = 'authority partial missing '.$section.' section';
                }
            }
        }

        if (! is_file($shopPartial)) {
            $missing[] = 'common-problem-shop-experience.blade.php partial';
        }

        if ($missing !== []) {
            return [
                new SeoAuditFinding(
                    id: 'problem_template_incomplete',
                    title: 'Problem authority template incomplete',
                    severity: 'high',
                    category: 'content',
                    message: 'Common problem pages do not project the full authority template.',
                    recommendation: 'Wire CommonProblemAuthorityProjection through show.blade.php and the authority partial (symptoms, causes, diagnostic process, repairs, FAQ).',
                    evidence: implode('; ', $missing),
                    authoritySource: SeoAuditAuthoritySource::Configuration,
                    channel: SeoAuditChannel::Structural,
                    verifiedAt: $verifiedAt,
                ),
            ];
        }

        return [
            new SeoAuditFinding(
                id: 'problem_authority_template',
                title: 'Problem authority template',
                severity: 'info',
                category: 'content',
                message: 'Common problem pages project the authority template (symptoms, causes, diagnostic process, repairs, FAQ).',
                recommendation: 'No action required.',
                evidence: 'CommonProblemAuthorityProjection + common-problem-authority partial. '.$deployLabel.'.',
                authoritySource: SeoAuditAuthoritySource::Configuration,
                channel: SeoAuditChannel::Structural,
                passed: true,
                verifiedAt: $verifiedAt,
            ),
        ];
    }
}
