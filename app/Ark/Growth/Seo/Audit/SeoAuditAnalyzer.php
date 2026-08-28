<?php

namespace App\Ark\Growth\Seo\Audit;

interface SeoAuditAnalyzer
{
    public function name(): string;

    /**
     * @return list<SeoAuditFinding>
     */
    public function analyze(): array;
}
