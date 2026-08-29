<?php

use App\Ark\Growth\Content\ContentBuilderSchema;
use App\Ark\Growth\Content\ContentDraftToCommonProblemConverter;
use App\Ark\Growth\Opportunities\OpportunityAcceptanceCriteriaTemplate;
use App\Ark\Growth\Opportunities\OpportunityAcceptanceEvaluator;
use App\Ark\Growth\Content\GeneratedCommonProblemRepository;
use App\Ark\Growth\Integrations\IndexNow\IndexNowNotifier;
use App\Ark\Growth\Integrations\SearchEngineNotificationService;
use App\Ark\Growth\Maintenance\GrowthMaintenancePipeline;
use App\Ark\Growth\Maintenance\GrowthSyncTaskKey;
use App\Ark\Growth\Models\GrowthGeneratedCommonProblem;
use App\Ark\Growth\Models\GrowthOpportunity;
use App\Ark\Growth\Models\GrowthSyncTask;
use App\Ark\Growth\Opportunities\GrowthOpportunityAction;
use App\Ark\Growth\Opportunities\GrowthOpportunityStatus;
use App\Ark\Growth\Seo\AutoCommonProblemPublisher;
use App\Ark\Operations\Leads\Public\CommonProblemRegistry;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config([
        'growth.seo_automation.auto_publish_enabled' => true,
        'growth.seo_automation.auto_publish_min_impressions' => 100,
        'growth.seo_automation.notify_search_engines' => true,
        'growth.seo_automation.google_indexing_enabled' => false,
    ]);
});

it('merges generated common problems into the registry after config pages', function (): void {
    GrowthGeneratedCommonProblem::query()->create([
        'slug' => 'auto-test-query',
        'problem' => [
            'slug' => 'auto-test-query',
            'title' => 'Auto Test Query',
            'meta_description' => 'Generated page summary.',
            'symptoms' => ['Symptom one'],
            'can_drive' => ['Stop if unsafe'],
            'common_causes' => ['Cause one'],
            'what_happens_next' => ['We inspect first'],
            'faq' => [['question' => 'Q?', 'answer' => 'A.']],
        ],
        'published_at' => now(),
    ]);

    expect(CommonProblemRegistry::find('auto-test-query'))->not->toBeNull()
        ->and(CommonProblemRegistry::find('auto-test-query')['title'])->toBe('Auto Test Query');
});

it('converts a seeded opportunity draft into a common problem shape', function (): void {
    $draft = [
        'title' => 'P0420 Code',
        'slug' => 'p0420',
        'summary' => 'P0420 catalyst efficiency in Demo City.',
        'symptoms' => ['Check engine light on'],
        'diagnosis' => 'We verify live data before recommending a catalytic converter.',
        'common_causes' => ['Upstream misfire'],
        'when_not_to_drive' => ['Flashing check engine light'],
        'faq' => [['question' => 'What is P0420?', 'answer' => 'Catalyst efficiency below threshold.']],
        'related_problems' => ['check-engine-light'],
    ];

    $problem = app(ContentDraftToCommonProblemConverter::class)->convert($draft, 'p0420');

    expect($problem['slug'])->toBe('p0420')
        ->and($problem['faq'])->toHaveCount(1)
        ->and($problem['related_problem_slugs'])->toContain('check-engine-light');
});

it('auto publishes a qualified create opportunity as a live common problem page', function (): void {
    $opportunity = GrowthOpportunity::factory()->create([
        'key' => 'create:p0420-catalyst',
        'action_type' => GrowthOpportunityAction::Create,
        'status' => GrowthOpportunityStatus::Discovered,
        'search_query' => 'p0420 catalyst',
        'title' => 'Create: P0420 Catalyst',
        'priority_score' => 900,
        'evidence' => [
            'facts' => [
                ['label' => '28-day impressions', 'value' => '2,400'],
                ['label' => 'Avg position', 'value' => '10.2'],
            ],
        ],
        'content_draft' => [
            'title' => 'P0420 Catalyst',
            'slug' => 'p0420-catalyst',
            'summary' => 'P0420 catalyst efficiency in Demo City.',
            'symptoms' => ['Check engine light on', 'Failed emissions'],
            'diagnosis' => 'We verify fuel trim and catalyst response before quoting converters.',
            'common_causes' => ['Misfire history', 'Oil consumption'],
            'when_not_to_drive' => ['Flashing check engine light'],
            'faq' => [['question' => 'What is P0420?', 'answer' => 'Catalyst efficiency below threshold.']],
            'related_services' => ['Check engine light diagnosis'],
            'related_problems' => ['check-engine-light'],
        ],
    ]);

    $draft = ContentBuilderSchema::normalize(
        $opportunity->content_draft,
        $opportunity->title,
        $opportunity->search_query,
    );

    expect(ContentBuilderSchema::incompleteRequiredKeys($draft))->toBe([]);

    $criteria = app(OpportunityAcceptanceEvaluator::class)->evaluate(
        $opportunity,
        OpportunityAcceptanceCriteriaTemplate::hydrate($opportunity->action_type, null),
    );

    expect(app(OpportunityAcceptanceEvaluator::class)->gateSatisfied($criteria, 'publish'))->toBeTrue();

    Http::fake([
        'https://api.indexnow.org/indexnow' => Http::response('', 202),
    ]);

    $shop = ShopSettings::current();
    $shop->update([
        'growth_integrations' => [
            'seo_automation' => ['enabled' => true],
            'indexnow' => ['key' => str_repeat('a', 32)],
            'google_indexing' => ['enabled' => false],
        ],
    ]);

    $published = app(AutoCommonProblemPublisher::class)->publishQualified();

    expect($published)->toBe(1)
        ->and(CommonProblemRegistry::find('p0420-catalyst'))->not->toBeNull()
        ->and($opportunity->fresh()->status)->toBe(GrowthOpportunityStatus::Published)
        ->and(GrowthGeneratedCommonProblem::query()->where('slug', 'p0420-catalyst')->exists())->toBeTrue();
});

it('records auto publish and search engine notify tasks during nightly maintenance', function (): void {
    Http::fake([
        'https://api.indexnow.org/indexnow' => Http::response('', 202),
    ]);

    app(GrowthMaintenancePipeline::class)->runNightly();

    expect(GrowthSyncTask::query()->find(GrowthSyncTaskKey::AutoPublish->value))->not->toBeNull()
        ->and(GrowthSyncTask::query()->find(GrowthSyncTaskKey::SearchEngineNotify->value))->not->toBeNull();
});

it('serves the indexnow key verification file', function (): void {
    $key = str_repeat('b', 32);

    ShopSettings::current()->update([
        'growth_integrations' => [
            'indexnow' => ['key' => $key],
        ],
    ]);

    $this->get('/indexnow-'.$key.'.txt')
        ->assertOk()
        ->assertSee($key, false);
});

it('submits sitemap and urls via indexnow when configured', function (): void {
    Http::fake([
        'https://api.indexnow.org/indexnow' => Http::response('', 202),
    ]);

    ShopSettings::current()->update([
        'growth_integrations' => [
            'indexnow' => ['key' => str_repeat('c', 32)],
        ],
    ]);

    $results = app(SearchEngineNotificationService::class)->notifyAfterMaintenance();

    expect($results['indexnow']['submitted'] ?? false)->toBeTrue();

    Http::assertSent(function ($request): bool {
        return $request->url() === 'https://api.indexnow.org/indexnow'
            && str_contains($request->body(), 'sitemap.xml');
    });
});
