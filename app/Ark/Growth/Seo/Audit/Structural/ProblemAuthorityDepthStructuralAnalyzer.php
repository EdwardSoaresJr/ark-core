<?php

namespace App\Ark\Growth\Seo\Audit\Structural;

use App\Ark\Growth\Seo\Audit\SeoAuditAnalyzer;
use App\Ark\Growth\Seo\Audit\SeoAuditAuthoritySource;
use App\Ark\Growth\Seo\Audit\SeoAuditChannel;
use App\Ark\Growth\Seo\Audit\SeoAuditFinding;
use App\Ark\Growth\Seo\Audit\SeoAuditVerification;
use App\Ark\Operations\Leads\Public\CommonProblemAuthorityWordCount;
use App\Ark\Operations\Leads\Public\CommonProblemRegistry;

final class ProblemAuthorityDepthStructuralAnalyzer implements SeoAuditAnalyzer
{
    private const MIN_WORDS = 300;

    public function name(): string
    {
        return 'problem_authority_depth';
    }

    public function analyze(): array
    {
        $verifiedAt = SeoAuditVerification::atRequestTime();
        $findings = [];

        foreach (CommonProblemRegistry::all() as $problem) {
            $slug = (string) ($problem['slug'] ?? '');
            $wordCount = CommonProblemAuthorityWordCount::count($problem);

            if ($wordCount >= self::MIN_WORDS) {
                continue;
            }

            $findings[] = new SeoAuditFinding(
                id: 'thin_authority_'.$slug,
                title: 'Thin problem authority',
                severity: 'medium',
                category: 'content',
                message: sprintf(
                    'Problem authority for "%s" has %d words — below the %d-word authority threshold.',
                    $problem['problem'] ?? $slug,
                    $wordCount,
                    self::MIN_WORDS,
                ),
                recommendation: 'Expand symptoms, causes, diagnostic process, typical repairs, and FAQ in CommonProblemRegistry — not meta description alone.',
                url: $slug !== '' ? url('/common-problems/'.$slug) : null,
                evidence: 'CommonProblemAuthorityWordCount from authority fields (symptoms, causes, FAQ, diagnostic process, repairs).',
                authoritySource: SeoAuditAuthoritySource::Configuration,
                channel: SeoAuditChannel::Structural,
                verifiedAt: $verifiedAt,
            );
        }

        if ($findings === []) {
            $findings[] = new SeoAuditFinding(
                id: 'problem_authority_depth',
                title: 'Problem authority depth',
                severity: 'info',
                category: 'content',
                message: 'Every common problem meets the authority word-count threshold.',
                recommendation: 'No action required.',
                evidence: 'CommonProblemAuthorityWordCount ≥ '.self::MIN_WORDS.' for all '.count(CommonProblemRegistry::all()).' problems. '.SeoAuditVerification::deploymentLabel().'.',
                authoritySource: SeoAuditAuthoritySource::Configuration,
                channel: SeoAuditChannel::Structural,
                passed: true,
                verifiedAt: $verifiedAt,
            );
        }

        return $findings;
    }
}
