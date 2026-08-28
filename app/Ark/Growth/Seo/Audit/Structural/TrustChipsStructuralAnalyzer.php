<?php

namespace App\Ark\Growth\Seo\Audit\Structural;

use App\Ark\Growth\Seo\Audit\SeoAuditAnalyzer;
use App\Ark\Growth\Seo\Audit\SeoAuditAuthoritySource;
use App\Ark\Growth\Seo\Audit\SeoAuditChannel;
use App\Ark\Growth\Seo\Audit\SeoAuditFinding;
use App\Ark\Growth\Seo\Audit\SeoAuditVerification;

/**
 * Homepage may project trust chips. Common Problems use quiet factual proof later
 * in the article (Theme v1 Phase 4) — chips between H1 and answer are forbidden.
 */
final class TrustChipsStructuralAnalyzer implements SeoAuditAnalyzer
{
    public function name(): string
    {
        return 'trust_chips';
    }

    public function analyze(): array
    {
        $verifiedAt = SeoAuditVerification::atRequestTime();
        $deployLabel = SeoAuditVerification::deploymentLabel();

        $chipsPartial = base_path('resources/views/partials/public/hero-trust-chips.blade.php');
        $homeHero = base_path('resources/views/partials/public/home-hero-positioning.blade.php');
        $problemShow = base_path('resources/views/public/common-problems/show.blade.php');

        $issues = [];

        if (! is_file($chipsPartial)) {
            $issues[] = 'hero-trust-chips.blade.php missing';
        }

        if (is_file($homeHero) && ! str_contains((string) file_get_contents($homeHero), 'hero-trust-chips')) {
            $issues[] = 'home-hero-positioning does not include hero-trust-chips';
        }

        if (is_file($problemShow) && str_contains((string) file_get_contents($problemShow), 'hero-trust-chips')) {
            $issues[] = 'common-problems/show still includes hero-trust-chips (answer-first forbids opening trust pills)';
        }

        if ($issues !== []) {
            return [
                new SeoAuditFinding(
                    id: 'trust_chips_missing',
                    title: 'Trust chips projection mismatch',
                    severity: 'medium',
                    category: 'trust',
                    message: 'Homepage should project trust chips; Common Problems must not open with a trust-pill forest.',
                    recommendation: 'Keep hero-trust-chips on home hero only. Common Problems use quiet factual proof after useful content.',
                    evidence: implode('; ', $issues),
                    authoritySource: SeoAuditAuthoritySource::Configuration,
                    channel: SeoAuditChannel::Structural,
                    verifiedAt: $verifiedAt,
                ),
            ];
        }

        return [
            new SeoAuditFinding(
                id: 'trust_chips',
                title: 'Trust chips',
                severity: 'info',
                category: 'trust',
                message: 'Homepage projects trust chips; Common Problems keep opening answer-first without pill forest.',
                recommendation: 'No action required.',
                evidence: 'hero-trust-chips on home-hero-positioning only; CP show uses quiet proof. '.$deployLabel.'.',
                authoritySource: SeoAuditAuthoritySource::Configuration,
                channel: SeoAuditChannel::Structural,
                passed: true,
                verifiedAt: $verifiedAt,
            ),
        ];
    }
}
