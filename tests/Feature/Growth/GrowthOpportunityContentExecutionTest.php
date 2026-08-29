<?php

use App\Ark\Growth\Integrations\SearchConsoleIngestService;
use App\Ark\Growth\Models\GrowthOpportunity;
use App\Ark\Growth\Opportunities\GrowthOpportunityAction;
use App\Ark\Growth\Opportunities\GrowthOpportunityStatus;
use App\Ark\Growth\Opportunities\OpportunityAcceptanceCriteriaTemplate;
use App\Ark\Growth\Opportunities\OpportunityQueueProjection;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
});

function contentExecutionAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole(ArkRole::Admin->value);

    return $admin;
}

it('seeds acceptance criteria and a complete content draft for create page opportunities', function (): void {
    app(SearchConsoleIngestService::class)->ingestDay(now());
    app(OpportunityQueueProjection::class)->resolve(5);

    $opportunity = GrowthOpportunity::query()->where('search_query', 'brake repair colorado springs')->firstOrFail();

    expect($opportunity->acceptance_criteria)->not->toBeNull()
        ->and(collect($opportunity->acceptance_criteria)->pluck('label'))->toContain('FAQ included')
        ->and($opportunity->content_draft)->not->toBeNull()
        ->and($opportunity->content_draft['slug'])->toBe('brake-repair-demo-city')
        ->and($opportunity->content_draft['summary'])->not->toBe('')
        ->and($opportunity->content_draft['symptoms'])->not->toBe([])
        ->and($opportunity->content_draft['faq'])->not->toBe([]);
});

it('blocks publish when the content draft is incomplete', function (): void {
    app(SearchConsoleIngestService::class)->ingestDay(now());
    app(OpportunityQueueProjection::class)->resolve(5);

    $opportunity = GrowthOpportunity::query()->where('search_query', 'brake repair colorado springs')->firstOrFail();
    $opportunity->update([
        'status' => GrowthOpportunityStatus::Building,
        'content_draft' => [
            'title' => 'Brake Repair Demo City',
            'slug' => 'brake-repair-demo-city',
            'summary' => '',
            'symptoms' => [],
            'diagnosis' => '',
            'common_causes' => [],
            'when_not_to_drive' => [],
            'faq' => [],
            'cta' => 'Talk to a service advisor',
            'related_services' => [],
            'related_problems' => [],
            'related_vehicles' => [],
        ],
    ]);

    $this->actingAs(contentExecutionAdmin())
        ->withSession([\App\Ark\Operations\Workstations\WorkstationPresence::SESSION_BIND_DISMISSED => true])
        ->patch(route('growth.opportunities.status', $opportunity), [
            'status' => GrowthOpportunityStatus::Published->value,
        ])
        ->assertSessionHasErrors('status');

    expect($opportunity->fresh()->status)->toBe(GrowthOpportunityStatus::Building);
});

it('allows publish after start without manually filling the checklist', function (): void {
    app(SearchConsoleIngestService::class)->ingestDay(now());
    app(OpportunityQueueProjection::class)->resolve(5);

    $opportunity = GrowthOpportunity::query()->where('search_query', 'brake repair colorado springs')->firstOrFail();

    $this->actingAs(contentExecutionAdmin())
        ->withSession([\App\Ark\Operations\Workstations\WorkstationPresence::SESSION_BIND_DISMISSED => true])
        ->post(route('growth.opportunities.start', $opportunity))
        ->assertRedirect(route('growth.opportunities.build', $opportunity));

    $opportunity->refresh();

    $this->actingAs(contentExecutionAdmin())
        ->withSession([\App\Ark\Operations\Workstations\WorkstationPresence::SESSION_BIND_DISMISSED => true])
        ->patch(route('growth.opportunities.status', $opportunity), [
            'status' => GrowthOpportunityStatus::Published->value,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($opportunity->fresh()->status)->toBe(GrowthOpportunityStatus::Published);
});

it('allows publish when all acceptance criteria are satisfied', function (): void {
    app(SearchConsoleIngestService::class)->ingestDay(now());
    app(OpportunityQueueProjection::class)->resolve(5);

    $opportunity = GrowthOpportunity::query()->where('search_query', 'brake repair colorado springs')->firstOrFail();
    $opportunity->update([
        'status' => GrowthOpportunityStatus::Building,
        'content_draft' => [
            'title' => 'Brake Repair Demo City',
            'slug' => 'brake-repair-demo-city',
            'summary' => 'Symptoms and repair guidance for brake repair in Demo City.',
            'symptoms' => ['Humming at speed', 'Noise changes in turns'],
            'diagnosis' => 'Lift and check for play at each corner.',
            'common_causes' => ['Worn bearing', 'Dry or damaged hub'],
            'when_not_to_drive' => ['Grinding noise', 'Loose wheel feel'],
            'faq' => [['question' => 'Is it safe?', 'answer' => 'Not for long distances.']],
            'cta' => 'Talk to a service advisor',
            'related_services' => ['Brake service'],
            'related_problems' => ['brake-noise'],
            'related_vehicles' => ['Subaru'],
        ],
        'acceptance_criteria' => collect(OpportunityAcceptanceCriteriaTemplate::forAction($opportunity->action_type))
            ->map(fn (array $item): array => [...$item, 'satisfied' => ($item['gate'] ?? 'publish') === 'publish'])
            ->all(),
    ]);

    $this->actingAs(contentExecutionAdmin())
        ->withSession([\App\Ark\Operations\Workstations\WorkstationPresence::SESSION_BIND_DISMISSED => true])
        ->patch(route('growth.opportunities.status', $opportunity), [
            'status' => GrowthOpportunityStatus::Published->value,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($opportunity->fresh()->status)->toBe(GrowthOpportunityStatus::Published);
});

it('strips Create and Improve prefixes from page draft titles', function (): void {
    app(SearchConsoleIngestService::class)->ingestDay(now());
    app(OpportunityQueueProjection::class)->resolve(5);

    $opportunity = GrowthOpportunity::query()->where('search_query', 'brake repair colorado springs')->firstOrFail();
    $opportunity->update([
        'content_draft' => [
            'title' => 'Create: Brake Repair Demo City',
            'slug' => 'brake-repair-demo-city',
            'summary' => 'Brakes grinding or squealing in Demo City?',
            'symptoms' => ['Squeal when slowing down'],
            'diagnosis' => 'We inspect pads and rotors.',
            'common_causes' => ['Worn pads'],
            'when_not_to_drive' => ['Grinding metal sounds'],
            'faq' => [['question' => 'Is it safe?', 'answer' => 'Drive minimally.']],
            'cta' => 'Talk to a service advisor',
            'related_services' => ['Brake inspection'],
            'related_problems' => ['brake-noise'],
            'related_vehicles' => [],
        ],
    ]);

    $this->actingAs(contentExecutionAdmin())
        ->withSession([\App\Ark\Operations\Workstations\WorkstationPresence::SESSION_BIND_DISMISSED => true])
        ->get(route('growth.opportunities.build', $opportunity))
        ->assertOk()
        ->assertSee('Brake Repair Demo City', false)
        ->assertDontSee('Create: Brake Repair Demo City', false);
});

it('renders the content builder checklist for an opportunity', function (): void {
    app(SearchConsoleIngestService::class)->ingestDay(now());
    app(OpportunityQueueProjection::class)->resolve(5);

    $opportunity = GrowthOpportunity::query()->where('search_query', 'brake repair colorado springs')->firstOrFail();

    $this->actingAs(contentExecutionAdmin())
        ->withSession([\App\Ark\Operations\Workstations\WorkstationPresence::SESSION_BIND_DISMISSED => true])
        ->get(route('growth.opportunities.build', $opportunity))
        ->assertOk()
        ->assertSee('Publish checklist')
        ->assertSee('Edit page content')
        ->assertSee('Indexed by Google')
        ->assertSee('brake-repair-demo-city');
});

it('renders staff preview for draft common problem content', function (): void {
    // Regression: preview reuses public.common-problems.show and must pass $authority
    // (production 5a077210 — Undefined variable $authority).
    $opportunity = GrowthOpportunity::factory()->create([
        'key' => 'create:preview-authority-regression',
        'action_type' => GrowthOpportunityAction::Create,
        'status' => GrowthOpportunityStatus::Building,
        'search_query' => 'preview authority regression',
        'title' => 'Create: Preview Authority Regression',
        'content_draft' => [
            'title' => 'Preview Authority Regression',
            'slug' => 'preview-authority-regression',
            'summary' => 'Staff preview must package problem authority before rendering.',
            'symptoms' => ['Preview fails without authority'],
            'diagnosis' => 'Pass CommonProblemAuthorityProjection into the public show view.',
            'common_causes' => ['Missing authority on growth preview'],
            'when_not_to_drive' => ['Do not ship preview without shared public view data'],
            'faq' => [['question' => 'Why preview?', 'answer' => 'Draft review before publish.']],
            'cta' => 'Talk to a service advisor',
            'related_services' => [],
            'related_problems' => [],
            'related_vehicles' => [],
        ],
    ]);

    $this->actingAs(contentExecutionAdmin())
        ->withSession([\App\Ark\Operations\Workstations\WorkstationPresence::SESSION_BIND_DISMISSED => true])
        ->get(route('growth.opportunities.preview', $opportunity))
        ->assertOk()
        ->assertSee('Staff preview', false)
        ->assertSee('Draft only', false)
        ->assertSee('Preview Authority Regression', false)
        ->assertSee('noindex, nofollow', false)
        ->assertSee('public-page-title', false)
        ->assertSee('Staff preview must package problem authority before rendering.', false);
});

it('content builder preview link targets staff preview route not public slug', function (): void {
    app(SearchConsoleIngestService::class)->ingestDay(now());
    app(OpportunityQueueProjection::class)->resolve(5);

    $opportunity = GrowthOpportunity::query()->where('search_query', 'brake repair colorado springs')->firstOrFail();

    $this->actingAs(contentExecutionAdmin())
        ->withSession([\App\Ark\Operations\Workstations\WorkstationPresence::SESSION_BIND_DISMISSED => true])
        ->get(route('growth.opportunities.build', $opportunity))
        ->assertOk()
        ->assertSee(route('growth.opportunities.preview', $opportunity), false)
        ->assertSee('/common-problems/brake-repair-demo-city', false);
});

it('shows posture counts and validated badge on the queue', function (): void {
    app(SearchConsoleIngestService::class)->ingestDay(now());
    app(OpportunityQueueProjection::class)->resolve(5);

    $opportunity = GrowthOpportunity::query()->firstOrFail();
    $opportunity->update([
        'status' => GrowthOpportunityStatus::Validated,
        'validated_at' => now(),
        'measurement' => [
            'period_days' => 60,
            'delta' => [
                'impressions' => 2134,
                'clicks' => 103,
                'leads' => 14,
                'repair_orders' => 8,
                'revenue_cents' => 648200,
            ],
        ],
    ]);

    $this->actingAs(contentExecutionAdmin())
        ->withSession([\App\Ark\Operations\Workstations\WorkstationPresence::SESSION_BIND_DISMISSED => true])
        ->get(route('growth.opportunities.index'))
        ->assertOk()
        ->assertSee('Validated')
        ->assertSee('Start →');
});
