<?php

namespace App\Ark\Growth\Seo\Audit;

final class RuntimeSeoAuditFinding
{
    public static function failure(
        string $analyzer,
        string $severity,
        string $category,
        string $message,
        string $recommendation,
        SeoAuditAuthoritySource $authoritySource,
        ?string $path = null,
        ?string $evidence = null,
    ): SeoAuditFinding {
        return new SeoAuditFinding(
            id: $analyzer,
            title: self::titleFromAnalyzer($analyzer),
            severity: $severity,
            category: $category,
            message: $message,
            recommendation: $recommendation,
            url: $path,
            evidence: $evidence,
            authoritySource: $authoritySource,
            channel: SeoAuditChannel::Runtime,
            verifiedAt: SeoAuditVerification::atRequestTime(),
        );
    }

    private static function titleFromAnalyzer(string $analyzer): string
    {
        return str($analyzer)->replace('_', ' ')->title()->toString();
    }
}
