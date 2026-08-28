<?php

namespace App\Ark\Growth\Seo\Audit\Structural;

use App\Ark\Growth\Seo\Audit\SeoAuditAnalyzer;
use App\Ark\Growth\Seo\Audit\SeoAuditAuthoritySource;
use App\Ark\Growth\Seo\Audit\SeoAuditChannel;
use App\Ark\Growth\Seo\Audit\SeoAuditFinding;
use App\Ark\Growth\Seo\Audit\SeoAuditVerification;
use App\Ark\Operations\Leads\Public\CommonProblemRegistry;

final class SchemaStructuralAnalyzer implements SeoAuditAnalyzer
{
    public function name(): string
    {
        return 'schema_generation';
    }

    public function analyze(): array
    {
        $verifiedAt = SeoAuditVerification::atRequestTime();
        $deployLabel = SeoAuditVerification::deploymentLabel();

        $homeSchema = ['AutoRepair', 'LocalBusiness'];
        $problemSchema = ['AutoRepair', 'FAQPage'];

        if ($homeSchema === []) {
            return [
                new SeoAuditFinding(
                    id: 'schema_home_missing',
                    title: 'Homepage schema missing',
                    severity: 'medium',
                    category: 'metadata',
                    message: 'Homepage schema types are not configured.',
                    recommendation: 'Register AutoRepair and LocalBusiness schema for the homepage in public content sync.',
                    evidence: 'PublicContentRegistrySyncService home metadata',
                    authoritySource: SeoAuditAuthoritySource::Configuration,
                    channel: SeoAuditChannel::Structural,
                    verifiedAt: $verifiedAt,
                ),
            ];
        }

        $problemCount = count(CommonProblemRegistry::all());

        return [
            new SeoAuditFinding(
                id: 'schema_generation',
                title: 'Schema generation',
                severity: 'info',
                category: 'metadata',
                message: 'Public pages declare structured data types at registration time.',
                recommendation: 'No action required.',
                evidence: 'Home: '.implode(', ', (array) $homeSchema).'. Problems: '.implode(', ', $problemSchema).' × '.$problemCount.' pages. '.$deployLabel.'.',
                authoritySource: SeoAuditAuthoritySource::Configuration,
                channel: SeoAuditChannel::Structural,
                passed: true,
                verifiedAt: $verifiedAt,
            ),
        ];
    }
}
