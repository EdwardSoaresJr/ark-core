<?php

namespace App\Ark\Growth\Seo\Audit;

use App\Ark\Growth\Seo\Audit\Analyzers\BrokenLinksAnalyzer;
use App\Ark\Growth\Seo\Audit\Analyzers\CanonicalIssueAnalyzer;
use App\Ark\Growth\Seo\Audit\Analyzers\MissingAltTextAnalyzer;
use App\Ark\Growth\Seo\Audit\Analyzers\MultipleH1Analyzer;
use App\Ark\Growth\Seo\Audit\Analyzers\OrphanPageAnalyzer;
use App\Ark\Growth\Seo\Audit\Analyzers\SlowPageAnalyzer;
use App\Ark\Growth\Seo\Audit\Structural\CtaConsistencyStructuralAnalyzer;
use App\Ark\Growth\Seo\Audit\Structural\FooterStructuralAnalyzer;
use App\Ark\Growth\Seo\Audit\Structural\ProblemAuthorityDepthStructuralAnalyzer;
use App\Ark\Growth\Seo\Audit\Structural\ProblemAuthorityTemplateStructuralAnalyzer;
use App\Ark\Growth\Seo\Audit\Structural\PublicSeoMetadataStructuralAnalyzer;
use App\Ark\Growth\Seo\Audit\Structural\SchemaStructuralAnalyzer;
use App\Ark\Growth\Seo\Audit\Structural\TrustChipsStructuralAnalyzer;

final class SeoAuditEngine
{
    /** @var list<SeoAuditAnalyzer> */
    private array $structuralAnalyzers;

    /** @var list<SeoAuditAnalyzer> */
    private array $runtimeAnalyzers;

    public function __construct()
    {
        $this->structuralAnalyzers = [
            new CtaConsistencyStructuralAnalyzer,
            new FooterStructuralAnalyzer,
            new TrustChipsStructuralAnalyzer,
            new ProblemAuthorityTemplateStructuralAnalyzer,
            new ProblemAuthorityDepthStructuralAnalyzer,
            new PublicSeoMetadataStructuralAnalyzer,
            new SchemaStructuralAnalyzer,
        ];

        $this->runtimeAnalyzers = [
            new BrokenLinksAnalyzer,
            new MissingAltTextAnalyzer,
            new CanonicalIssueAnalyzer,
            new SlowPageAnalyzer,
            new MultipleH1Analyzer,
            new OrphanPageAnalyzer,
        ];
    }

    public function registerStructural(SeoAuditAnalyzer $analyzer): void
    {
        $this->structuralAnalyzers[] = $analyzer;
    }

    public function registerRuntime(SeoAuditAnalyzer $analyzer): void
    {
        $this->runtimeAnalyzers[] = $analyzer;
    }

    /**
     * @return list<SeoAuditFinding>
     */
    public function run(): array
    {
        return array_merge(
            $this->runChannel($this->structuralAnalyzers),
            $this->runChannel($this->runtimeAnalyzers),
        );
    }

    /**
     * @param  list<SeoAuditAnalyzer>  $analyzers
     * @return list<SeoAuditFinding>
     */
    private function runChannel(array $analyzers): array
    {
        $findings = [];

        foreach ($analyzers as $analyzer) {
            $findings = array_merge($findings, $analyzer->analyze());
        }

        return $findings;
    }

    /**
     * @return array{
     *     findings: list<array<string, mixed>>,
     *     failures: list<array<string, mixed>>,
     *     counts: array{critical: int, warning: int, info: int, passed: int},
     *     channels: array<string, array<string, mixed>>
     * }
     */
    public function summarize(): array
    {
        $structural = $this->runChannel($this->structuralAnalyzers);
        $runtime = $this->runChannel($this->runtimeAnalyzers);
        $all = array_merge($structural, $runtime);

        $counts = ['critical' => 0, 'warning' => 0, 'info' => 0, 'passed' => 0];
        $failures = [];

        foreach ($all as $finding) {
            if ($finding->passed) {
                $counts['passed']++;

                continue;
            }

            $failures[] = $finding->toArray();

            match ($finding->severity) {
                'critical', 'high' => $counts['critical']++,
                'warning', 'medium' => $counts['warning']++,
                default => $counts['info']++,
            };
        }

        return [
            'findings' => array_map(fn (SeoAuditFinding $f): array => $f->toArray(), $all),
            'failures' => $failures,
            'counts' => $counts,
            'channels' => [
                SeoAuditChannel::Structural->value => $this->presentChannel(SeoAuditChannel::Structural, $structural),
                SeoAuditChannel::Runtime->value => $this->presentChannel(SeoAuditChannel::Runtime, $runtime),
            ],
        ];
    }

    /**
     * @param  list<SeoAuditFinding>  $findings
     * @return array<string, mixed>
     */
    private function presentChannel(SeoAuditChannel $channel, array $findings): array
    {
        $passed = 0;
        $failed = 0;

        foreach ($findings as $finding) {
            if ($finding->passed) {
                $passed++;
            } else {
                $failed++;
            }
        }

        return [
            'label' => $channel->label(),
            'description' => $channel->description(),
            'findings' => array_map(fn (SeoAuditFinding $f): array => $f->toArray(), $findings),
            'passed_count' => $passed,
            'failed_count' => $failed,
        ];
    }
}
