<?php

use App\Ark\Growth\Integrations\SearchConsoleIngestService;
use App\Ark\Growth\Models\GrowthLandingPageMetric;
use App\Ark\Growth\Models\GrowthOpportunity;
use App\Ark\Growth\Models\GrowthSearchQuery;
use App\Ark\Growth\Opportunities\OpportunityQueueProjection;
use App\Ark\Growth\Opportunities\GrowthOpportunityStatus;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
});

function growthAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole(ArkRole::Admin->value);

    return $admin;
}

it('stores search console rows as immutable daily snapshots', function (): void {
    $ingest = app(SearchConsoleIngestService::class);

    $ingest->ingestDay(Carbon::parse('2026-06-01'));
    $ingest->ingestDay(Carbon::parse('2026-06-02'));

    expect(GrowthSearchQuery::query()->where('query', 'wheel bearing noise')->count())->toBe(2)
        ->and(GrowthLandingPageMetric::query()->where('path', '/common-problems/check-engine-light')->count())->toBe(2);
});

it('returns the top five highest value seo tasks with evidence after sync', function (): void {
    app(SearchConsoleIngestService::class)->ingestDay(now());

    $queue = app(OpportunityQueueProjection::class)->resolve(5);

    expect($queue['opportunities'])->not->toBeEmpty()
        ->and(count($queue['opportunities']))->toBeLessThanOrEqual(5)
        ->and(collect($queue['opportunities'])->pluck('title')->join(' '))->not->toContain('Wheel Bearing Noise')
        ->and(collect($queue['opportunities'])->pluck('search_query'))->toContain('brake repair colorado springs')
        ->and($queue['opportunities'][0]['estimated_lift']['steps'])->not->toBeEmpty()
        ->and($queue['opportunities'][0]['evidence']['facts'])->not->toBeEmpty()
        ->and($queue['meta']['success_criteria'])->toContain('Top 5');
});

it('surfaces create page opportunities for high demand queries without matching content', function (): void {
    app(SearchConsoleIngestService::class)->ingestDay(now());
    app(OpportunityQueueProjection::class)->resolve(5);

    $create = GrowthOpportunity::query()->where('search_query', 'brake repair colorado springs')->first();

    expect($create)->not->toBeNull()
        ->and($create->title)->toContain('Create')
        ->and($create->status)->toBe(GrowthOpportunityStatus::Discovered)
        ->and(GrowthOpportunity::query()->where('search_query', 'p0171')->exists())->toBeFalse();
});

it('does not recommend creating a page that already exists', function (): void {
    app(SearchConsoleIngestService::class)->ingestDay(now());
    app(OpportunityQueueProjection::class)->resolve(5);

    expect(GrowthOpportunity::query()->where('search_query', 'wheel bearing noise')->exists())->toBeFalse();
});

it('surfaces improve page opportunities for landing pages with weak ctr', function (): void {
    app(SearchConsoleIngestService::class)->ingestDay(now());
    app(OpportunityQueueProjection::class)->resolve(5);

    $improve = GrowthOpportunity::query()
        ->where('landing_path', '/common-problems/check-engine-light')
        ->first();

    expect($improve)->not->toBeNull()
        ->and($improve->title)->toContain('Improve');
});

it('transitions opportunity status through growth lifecycle', function (): void {
    app(SearchConsoleIngestService::class)->ingestDay(now());
    app(OpportunityQueueProjection::class)->resolve(5);

    $opportunity = GrowthOpportunity::query()->firstOrFail();

    $opportunity->transitionTo(GrowthOpportunityStatus::Accepted);
    $opportunity->refresh();
    expect($opportunity->status)->toBe(GrowthOpportunityStatus::Accepted)
        ->and($opportunity->accepted_at)->not->toBeNull();

    $opportunity->transitionTo(GrowthOpportunityStatus::Building);
    $opportunity->transitionTo(GrowthOpportunityStatus::Published);
    $opportunity->transitionTo(GrowthOpportunityStatus::Measuring);
    $opportunity->transitionTo(GrowthOpportunityStatus::Validated);

    $opportunity->refresh();
    expect($opportunity->status)->toBe(GrowthOpportunityStatus::Validated)
        ->and($opportunity->validated_at)->not->toBeNull();
});

it('renders opportunities as the primary growth surface', function (): void {
    app(SearchConsoleIngestService::class)->ingestDay(now());

    $this->actingAs(growthAdmin())
        ->withSession([\App\Ark\Operations\Workstations\WorkstationPresence::SESSION_BIND_DISMISSED => true])
        ->get(route('growth.opportunities.index'))
        ->assertOk()
        ->assertSee('Opportunity Queue')
        ->assertSee('Brake Repair')
        ->assertSee('Estimated lift')
        ->assertSee('Show evidence', false);
});

it('redirects growth root to opportunities', function (): void {
    $this->actingAs(growthAdmin())
        ->withSession([\App\Ark\Operations\Workstations\WorkstationPresence::SESSION_BIND_DISMISSED => true])
        ->get('/app/growth')
        ->assertRedirect(route('growth.opportunities.index'));
});

it('restricts opportunities to growth access capability', function (): void {
    $advisor = User::factory()->create();
    $advisor->assignRole(ArkRole::Advisor->value);

    $this->actingAs($advisor)
        ->get(route('growth.opportunities.index'))
        ->assertForbidden();
});

it('sync command ingests fixture search console data', function (): void {
    config([
        'growth.integrations.google_search_console.enabled' => false,
        'growth.integrations.google_search_console.fixture_enabled' => true,
    ]);

    $this->artisan('growth:sync-search-console', ['--date' => '2026-06-15'])
        ->assertSuccessful();

    expect(GrowthSearchQuery::query()->whereDate('report_date', '2026-06-15')->count())->toBeGreaterThan(0);
});
