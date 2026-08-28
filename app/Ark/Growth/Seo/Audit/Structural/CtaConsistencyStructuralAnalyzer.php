<?php

namespace App\Ark\Growth\Seo\Audit\Structural;

use App\Ark\Growth\Seo\Audit\SeoAuditAnalyzer;
use App\Ark\Growth\Seo\Audit\SeoAuditAuthoritySource;
use App\Ark\Growth\Seo\Audit\SeoAuditChannel;
use App\Ark\Growth\Seo\Audit\SeoAuditFinding;
use App\Ark\Growth\Seo\Audit\SeoAuditVerification;
use App\Ark\Operations\Leads\Public\CommonProblemFormCopy;
use App\Ark\Operations\Leads\Public\PublicLeadFormCopy;

final class CtaConsistencyStructuralAnalyzer implements SeoAuditAnalyzer
{
    private const STALE_SUBMIT_LABEL = 'Send request';

    /** @var list<string> */
    private const PUBLIC_VIEW_ROOTS = [
        'resources/views/public',
        'resources/views/partials/public',
    ];

    public function name(): string
    {
        return 'cta_consistency';
    }

    public function analyze(): array
    {
        $violations = $this->findStaleSubmitLabels();
        $verifiedAt = SeoAuditVerification::atRequestTime();
        $deployLabel = SeoAuditVerification::deploymentLabel();

        if ($violations !== []) {
            return [
                new SeoAuditFinding(
                    id: 'cta_inconsistent',
                    title: 'Inconsistent CTA',
                    severity: 'medium',
                    category: 'conversion',
                    message: 'One or more public lead forms still use a legacy submit label instead of the shared authority constant.',
                    recommendation: 'Route every public lead form through PublicLeadFormCopy::SUBMIT_LABEL (or CommonProblemFormCopy::SUBMIT_LABEL).',
                    evidence: implode('; ', $violations),
                    authoritySource: SeoAuditAuthoritySource::Configuration,
                    channel: SeoAuditChannel::Structural,
                    verifiedAt: $verifiedAt,
                ),
            ];
        }

        return [
            new SeoAuditFinding(
                id: 'cta_consistency',
                title: 'CTA consistency',
                severity: 'info',
                category: 'conversion',
                message: 'Every public lead form references the shared submit label authority.',
                recommendation: 'No action required.',
                evidence: 'PublicLeadFormCopy::SUBMIT_LABEL = "'.PublicLeadFormCopy::SUBMIT_LABEL.'". CommonProblemFormCopy::SUBMIT_LABEL matches. '.$deployLabel.'.',
                authoritySource: SeoAuditAuthoritySource::Configuration,
                channel: SeoAuditChannel::Structural,
                passed: true,
                verifiedAt: $verifiedAt,
            ),
        ];
    }

    /**
     * @return list<string>
     */
    private function findStaleSubmitLabels(): array
    {
        if (CommonProblemFormCopy::SUBMIT_LABEL !== PublicLeadFormCopy::SUBMIT_LABEL) {
            return ['CommonProblemFormCopy::SUBMIT_LABEL diverges from PublicLeadFormCopy::SUBMIT_LABEL'];
        }

        $violations = [];

        foreach (self::PUBLIC_VIEW_ROOTS as $root) {
            $path = base_path($root);

            if (! is_dir($path)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $relative = str_replace(base_path().'/', '', $file->getPathname());
                $contents = (string) file_get_contents($file->getPathname());

                if (str_contains($contents, "'".self::STALE_SUBMIT_LABEL."'")
                    || str_contains($contents, '"'.self::STALE_SUBMIT_LABEL.'"')) {
                    $violations[] = $relative.' still references "'.self::STALE_SUBMIT_LABEL.'"';
                }
            }
        }

        return $violations;
    }
}
