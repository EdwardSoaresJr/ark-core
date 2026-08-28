<?php

namespace App\Ark\Growth\Maintenance;

use App\Ark\Growth\Content\PublicContentRegistrySyncService;
use App\Ark\Growth\Integrations\GoogleBusinessProfileSyncService;
use App\Ark\Growth\Integrations\SearchConsoleIngestService;
use App\Ark\Growth\Models\GrowthOpportunity;
use App\Ark\Growth\Opportunities\OpportunityQueueRepository;
use App\Ark\Growth\Integrations\SearchEngineNotificationService;
use App\Ark\Growth\Seo\Audit\SeoAuditEngine;
use App\Ark\Growth\Seo\AutoCommonProblemPublisher;
use Illuminate\Support\Carbon;

/**
 * Operators perform business actions. ARK performs system actions.
 */
final class GrowthMaintenancePipeline
{
    public function __construct(
        private readonly SearchConsoleIngestService $searchConsole,
        private readonly GoogleBusinessProfileSyncService $businessProfile,
        private readonly PublicContentRegistrySyncService $publicContent,
        private readonly OpportunityQueueRepository $opportunities,
        private readonly SeoAuditEngine $seoAudit,
        private readonly AutoCommonProblemPublisher $autoPublisher,
        private readonly SearchEngineNotificationService $searchEngines,
        private readonly GrowthSyncTaskRecorder $recorder,
    ) {}

    public function runNightly(?Carbon $reportDate = null): void
    {
        $this->syncSearchConsole($reportDate ?? now()->subDay()->startOfDay());
        $this->businessProfile->sync();
        $this->syncPublicContent();
        $this->runSeoAudit();
        $this->recalculateOpportunities();
        $this->autoPublishQualifiedPages();
        $this->notifySearchEngines();
        $this->rebuildBriefing();
    }

    public function runOnPublish(GrowthOpportunity $opportunity): void
    {
        $this->syncPublicContent();
        $this->runSeoAudit();
        $this->recalculateOpportunities();
        $this->linkPublishedOpportunity($opportunity);
    }

    public function runOnContentChange(GrowthOpportunity $opportunity): void
    {
        $this->runSeoAudit();
        $this->recalculateOpportunities();
    }

    public function syncSearchConsole(Carbon $reportDate): void
    {
        $this->recorder->markRunning(GrowthSyncTaskKey::SearchConsole);

        try {
            $rows = $this->searchConsole->ingestDay($reportDate);

            if ($rows === 0) {
                $this->recorder->recordSkipped(
                    GrowthSyncTaskKey::SearchConsole,
                    'Search Console is not configured or returned no rows for '.$reportDate->toDateString().'.',
                    ['report_date' => $reportDate->toDateString(), 'rows' => 0],
                );

                return;
            }

            $this->recorder->recordSuccess(
                GrowthSyncTaskKey::SearchConsole,
                "Imported {$rows} Search Console rows for {$reportDate->toDateString()}.",
                ['report_date' => $reportDate->toDateString(), 'rows' => $rows],
            );
        } catch (\Throwable $exception) {
            report($exception);

            $this->recorder->recordFailure(
                GrowthSyncTaskKey::SearchConsole,
                $exception->getMessage(),
                ['report_date' => $reportDate->toDateString()],
            );
        }
    }

    public function syncPublicContent(): void
    {
        $this->recorder->markRunning(GrowthSyncTaskKey::PublicContent);

        try {
            $count = $this->publicContent->sync();

            $this->recorder->recordSuccess(
                GrowthSyncTaskKey::PublicContent,
                "Synchronized {$count} public pages into the content registry.",
                ['pages' => $count, 'base_url' => $this->publicContent->baseUrl()],
            );
        } catch (\Throwable $exception) {
            report($exception);

            $this->recorder->recordFailure(
                GrowthSyncTaskKey::PublicContent,
                $exception->getMessage(),
            );
        }
    }

    public function runSeoAudit(): void
    {
        $this->recorder->markRunning(GrowthSyncTaskKey::SeoAudit);

        try {
            $summary = $this->seoAudit->summarize();

            $this->recorder->recordSuccess(
                GrowthSyncTaskKey::SeoAudit,
                'SEO audit completed.',
                $summary,
            );
        } catch (\Throwable $exception) {
            report($exception);

            $this->recorder->recordFailure(
                GrowthSyncTaskKey::SeoAudit,
                $exception->getMessage(),
            );
        }
    }

    public function recalculateOpportunities(): void
    {
        $this->recorder->markRunning(GrowthSyncTaskKey::OpportunityQueue);

        try {
            $synced = $this->opportunities->syncDiscovered();

            $this->recorder->recordSuccess(
                GrowthSyncTaskKey::OpportunityQueue,
                "Opportunity queue recalculated — {$synced} discovered rows synchronized.",
                ['discovered_synced' => $synced],
            );
        } catch (\Throwable $exception) {
            report($exception);

            $this->recorder->recordFailure(
                GrowthSyncTaskKey::OpportunityQueue,
                $exception->getMessage(),
            );
        }
    }

    public function rebuildBriefing(): void
    {
        $this->recorder->markRunning(GrowthSyncTaskKey::Briefing);

        $this->recorder->recordSkipped(
            GrowthSyncTaskKey::Briefing,
            'Operations briefing rebuild is reserved for the nightly digest integration.',
            ['integrated' => false],
        );
    }

    public function autoPublishQualifiedPages(): void
    {
        $this->recorder->markRunning(GrowthSyncTaskKey::AutoPublish);

        try {
            $count = $this->autoPublisher->publishQualified();

            $this->recorder->recordSuccess(
                GrowthSyncTaskKey::AutoPublish,
                $count > 0
                    ? "Auto-published {$count} Common Problems page(s) from search demand."
                    : 'No qualified Create opportunities to auto-publish tonight.',
                ['pages_published' => $count],
            );
        } catch (\Throwable $exception) {
            report($exception);

            $this->recorder->recordFailure(
                GrowthSyncTaskKey::AutoPublish,
                $exception->getMessage(),
            );
        }
    }

    public function notifySearchEngines(): void
    {
        $this->recorder->markRunning(GrowthSyncTaskKey::SearchEngineNotify);

        try {
            $results = $this->searchEngines->notifyAfterMaintenance();

            if (($results['skipped'] ?? false) === true) {
                $this->recorder->recordSkipped(
                    GrowthSyncTaskKey::SearchEngineNotify,
                    (string) ($results['reason'] ?? 'Search engine notifications disabled.'),
                    $results,
                );

                return;
            }

            $this->recorder->recordSuccess(
                GrowthSyncTaskKey::SearchEngineNotify,
                'Search engine notifications sent.',
                $results,
            );
        } catch (\Throwable $exception) {
            report($exception);

            $this->recorder->recordFailure(
                GrowthSyncTaskKey::SearchEngineNotify,
                $exception->getMessage(),
            );
        }
    }

    private function linkPublishedOpportunity(GrowthOpportunity $opportunity): void
    {
        if ($opportunity->growth_content_id !== null) {
            return;
        }

        if (filled($opportunity->landing_path)) {
            $content = app(\App\Ark\Growth\Content\ContentRegistry::class)
                ->findByPath((string) $opportunity->landing_path);

            if ($content !== null) {
                $opportunity->growth_content_id = $content->id;
                $opportunity->save();
            }
        }
    }
}
