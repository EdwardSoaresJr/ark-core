<?php

namespace App\Ark\Growth\Seo;

use App\Ark\Growth\Content\ContentBuilderSchema;
use App\Ark\Growth\Content\ContentDraftToCommonProblemConverter;
use App\Ark\Growth\Content\ContentRegistry;
use App\Ark\Growth\Content\GeneratedCommonProblemRepository;
use App\Ark\Growth\Events\GrowthOpportunityPublished;
use App\Ark\Growth\Integrations\SearchEngineNotificationService;
use App\Ark\Growth\Models\GrowthOpportunity;
use App\Ark\Growth\Opportunities\GrowthOpportunityAction;
use App\Ark\Growth\Opportunities\GrowthOpportunityStatus;
use App\Ark\Growth\Opportunities\OpportunityAcceptanceCriteriaTemplate;
use App\Ark\Growth\Opportunities\OpportunityAcceptanceEvaluator;
use App\Ark\Growth\Opportunities\OpportunityQueueRepository;
use App\Ark\Growth\Settings\GrowthIntegrationSettings;
use App\Ark\Operations\Leads\Public\CommonProblemRegistry;
use Illuminate\Support\Str;

/**
 * Publishes high-confidence Create opportunities as live Common Problems pages.
 */
final class AutoCommonProblemPublisher
{
    public function __construct(
        private readonly OpportunityQueueRepository $opportunities,
        private readonly OpportunityAcceptanceEvaluator $acceptance,
        private readonly ContentDraftToCommonProblemConverter $converter,
        private readonly GeneratedCommonProblemRepository $generated,
        private readonly ContentRegistry $contentRegistry,
        private readonly SearchEngineNotificationService $searchEngines,
    ) {}

    public function publishQualified(): int
    {
        if (! config('growth.seo_automation.auto_publish_enabled', true)) {
            return 0;
        }

        if (! GrowthIntegrationSettings::current()->isSeoAutomationEnabled()) {
            return 0;
        }

        $max = (int) config('growth.seo_automation.auto_publish_max_per_night', 2);
        $published = 0;
        $paths = [];

        $candidates = GrowthOpportunity::query()
            ->where('action_type', GrowthOpportunityAction::Create)
            ->where('status', GrowthOpportunityStatus::Discovered)
            ->orderByDesc('priority_score')
            ->limit(20)
            ->get();

        foreach ($candidates as $opportunity) {
            if ($published >= $max) {
                break;
            }

            $opportunity = $this->opportunities->ensureDraft($opportunity);

            if (! $this->qualifies($opportunity)) {
                continue;
            }

            $draft = ContentBuilderSchema::normalize(
                $opportunity->content_draft,
                $opportunity->title,
                $opportunity->search_query,
            );

            $problem = $this->converter->convert($draft, $opportunity->search_query);

            if (CommonProblemRegistry::find((string) $problem['slug']) !== null) {
                continue;
            }

            $this->generated->storeFromOpportunity($opportunity, $problem);

            $this->contentRegistry->registerOrUpdate((string) $problem['slug'], [
                'template' => 'common_problems',
                'title' => (string) $problem['title'],
                'path' => '/common-problems/'.$problem['slug'],
                'published_at' => now(),
                'indexable' => true,
                'priority' => 75,
                'metadata' => [
                    'meta_description' => (string) $problem['meta_description'],
                    'schema_types' => ['AutoRepair', 'FAQPage'],
                    'source' => 'auto_publish',
                    'search_query' => $opportunity->search_query,
                ],
            ]);

            $opportunity->landing_path = '/common-problems/'.$problem['slug'];
            $opportunity->transitionTo(GrowthOpportunityStatus::Accepted);
            $opportunity->transitionTo(GrowthOpportunityStatus::Building);
            $opportunity->transitionTo(GrowthOpportunityStatus::Published);

            GrowthOpportunityPublished::dispatch($opportunity->fresh());

            $paths[] = (string) $opportunity->landing_path;
            $published++;
        }

        if ($paths !== []) {
            $this->searchEngines->notifyPaths($paths);
        }

        return $published;
    }

    private function qualifies(GrowthOpportunity $opportunity): bool
    {
        if ($opportunity->action_type !== GrowthOpportunityAction::Create) {
            return false;
        }

        if ($opportunity->status !== GrowthOpportunityStatus::Discovered) {
            return false;
        }

        $draft = ContentBuilderSchema::normalize(
            $opportunity->content_draft,
            $opportunity->title,
            $opportunity->search_query,
        );

        if (ContentBuilderSchema::needsSeeding($draft)) {
            return false;
        }

        $criteria = $this->acceptance->evaluate(
            $opportunity,
            OpportunityAcceptanceCriteriaTemplate::hydrate(
                $opportunity->action_type,
                $opportunity->acceptance_criteria,
            ),
        );

        if (! $this->acceptance->gateSatisfied($criteria, 'publish')) {
            return false;
        }

        $impressions = $this->impressionsFromEvidence($opportunity);
        $position = $this->positionFromEvidence($opportunity);
        $minImpressions = (int) config('growth.seo_automation.auto_publish_min_impressions', 500);
        $maxPosition = (float) config('growth.seo_automation.auto_publish_max_position', 15.0);

        if ($impressions < $minImpressions) {
            return false;
        }

        if ($position !== null && $position > $maxPosition) {
            return false;
        }

        $slug = Str::slug((string) $draft['slug']);

        return $slug !== '' && CommonProblemRegistry::find($slug) === null;
    }

    private function impressionsFromEvidence(GrowthOpportunity $opportunity): int
    {
        foreach ($opportunity->evidence['facts'] ?? [] as $fact) {
            if (($fact['label'] ?? '') === '28-day impressions') {
                return (int) preg_replace('/\D+/', '', (string) ($fact['value'] ?? '0'));
            }
        }

        return 0;
    }

    private function positionFromEvidence(GrowthOpportunity $opportunity): ?float
    {
        foreach ($opportunity->evidence['facts'] ?? [] as $fact) {
            if (($fact['label'] ?? '') === 'Avg position') {
                $value = (string) ($fact['value'] ?? '');

                return is_numeric($value) ? (float) $value : null;
            }
        }

        return null;
    }
}
