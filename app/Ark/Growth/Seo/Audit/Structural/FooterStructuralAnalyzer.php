<?php

namespace App\Ark\Growth\Seo\Audit\Structural;

use App\Ark\Growth\Seo\Audit\SeoAuditAnalyzer;
use App\Ark\Growth\Seo\Audit\SeoAuditAuthoritySource;
use App\Ark\Growth\Seo\Audit\SeoAuditChannel;
use App\Ark\Growth\Seo\Audit\SeoAuditFinding;
use App\Ark\Growth\Seo\Audit\SeoAuditVerification;

final class FooterStructuralAnalyzer implements SeoAuditAnalyzer
{
    public function name(): string
    {
        return 'footer_projection';
    }

    public function analyze(): array
    {
        $path = base_path('resources/views/partials/customer/site-footer.blade.php');
        $verifiedAt = SeoAuditVerification::atRequestTime();
        $deployLabel = SeoAuditVerification::deploymentLabel();

        if (! is_file($path)) {
            return [
                new SeoAuditFinding(
                    id: 'footer_missing_authority',
                    title: 'Missing footer authority',
                    severity: 'high',
                    category: 'trust',
                    message: 'The shared public footer partial is missing from the codebase.',
                    recommendation: 'Restore site-footer.blade.php and project CustomerSurfaceFooterData on every public page.',
                    evidence: 'Expected resources/views/partials/customer/site-footer.blade.php',
                    authoritySource: SeoAuditAuthoritySource::Configuration,
                    channel: SeoAuditChannel::Structural,
                    verifiedAt: $verifiedAt,
                ),
            ];
        }

        $contents = (string) file_get_contents($path);
        $required = [
            'CustomerSurfaceFooterData' => 'Footer data authority',
            'customer-footer' => 'Customer footer markup',
            'google_maps_url' => 'Maps link authority',
            'portal_url' => 'Portal link authority',
        ];

        $missing = [];

        foreach ($required as $needle => $label) {
            if (! str_contains($contents, $needle)) {
                $missing[] = $label.' ('.$needle.')';
            }
        }

        if ($missing !== []) {
            return [
                new SeoAuditFinding(
                    id: 'footer_incomplete',
                    title: 'Incomplete footer projection',
                    severity: 'high',
                    category: 'trust',
                    message: 'The public footer partial exists but does not project the full CustomerSurfaceFooterData contract.',
                    recommendation: 'Ensure site-footer.blade.php includes CustomerSurfaceFooterData, customer-footer slot, Maps, and Sign In / My Account links.',
                    evidence: 'Missing: '.implode(', ', $missing),
                    authoritySource: SeoAuditAuthoritySource::Configuration,
                    channel: SeoAuditChannel::Structural,
                    verifiedAt: $verifiedAt,
                ),
            ];
        }

        $shellPath = base_path('resources/views/components/customer/shell.blade.php');
        $shellContents = is_file($shellPath) ? (string) file_get_contents($shellPath) : '';
        $shellIncludesFooter = str_contains($shellContents, 'partials.customer.site-footer');

        if (! $shellIncludesFooter) {
            return [
                new SeoAuditFinding(
                    id: 'footer_not_wired',
                    title: 'Footer not wired to customer shell',
                    severity: 'high',
                    category: 'trust',
                    message: 'CustomerSurfaceFooterData exists but the customer shell does not include the shared footer partial.',
                    recommendation: 'Include partials.public.site-footer (or customer-footer slot content) in x-customer.shell.',
                    evidence: 'resources/views/components/customer/shell.blade.php',
                    authoritySource: SeoAuditAuthoritySource::Configuration,
                    channel: SeoAuditChannel::Structural,
                    verifiedAt: $verifiedAt,
                ),
            ];
        }

        return [
            new SeoAuditFinding(
                id: 'footer_projection',
                title: 'Footer projection',
                severity: 'info',
                category: 'trust',
                message: 'Public pages project CustomerSurfaceFooterData through the customer shell.',
                recommendation: 'No action required.',
                evidence: 'site-footer.blade.php + customer shell. '.$deployLabel.'.',
                authoritySource: SeoAuditAuthoritySource::Configuration,
                channel: SeoAuditChannel::Structural,
                passed: true,
                verifiedAt: $verifiedAt,
            ),
        ];
    }
}
