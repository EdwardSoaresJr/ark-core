<?php

use App\Ark\Growth\Events\GrowthOpportunityContentUpdated;
use App\Ark\Growth\Events\GrowthOpportunityPublished;
use App\Ark\Growth\Jobs\RunGrowthContentMaintenanceJob;
use App\Ark\Growth\Jobs\RunGrowthNightlyMaintenanceJob;
use App\Ark\Growth\Jobs\RunGrowthPublishMaintenanceJob;
use App\Ark\Growth\Maintenance\GrowthMaintenancePipeline;
use App\Ark\Growth\Maintenance\GrowthSyncTaskKey;
use App\Ark\Growth\Maintenance\GrowthSyncTaskRecorder;
use App\Ark\Growth\Maintenance\GrowthSyncTaskStatus;
use App\Ark\Growth\Models\GrowthContent;
use App\Ark\Growth\Models\GrowthLocationMetric;
use App\Ark\Growth\Models\GrowthOpportunity;
use App\Ark\Growth\Models\GrowthSearchQuery;
use App\Ark\Growth\Models\GrowthSyncTask;
use App\Ark\Growth\Opportunities\GrowthOpportunityStatus;
use App\Ark\Operations\Workstations\WorkstationPresence;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);

    config([
        'growth.integrations.google_search_console.enabled' => false,
        'growth.integrations.google_search_console.fixture_enabled' => true,
        'growth.integrations.google_business_profile.fixture_enabled' => true,
    ]);
});

it('runs nightly maintenance pipeline and records sync tasks', function (): void {
    app(GrowthMaintenancePipeline::class)->runNightly();

    expect(GrowthSyncTask::query()->count())->toBeGreaterThanOrEqual(5)
        ->and(GrowthSearchQuery::query()->count())->toBeGreaterThan(0)
        ->and(GrowthLocationMetric::query()->count())->toBeGreaterThan(0)
        ->and(GrowthContent::query()->count())->toBeGreaterThan(0);

    $searchConsole = GrowthSyncTask::query()->find(GrowthSyncTaskKey::SearchConsole->value);
    expect($searchConsole)->not->toBeNull()
        ->and($searchConsole->status)->toBe(GrowthSyncTaskStatus::Success);

    $businessProfile = GrowthSyncTask::query()->find(GrowthSyncTaskKey::GoogleBusinessProfile->value);
    expect($businessProfile)->not->toBeNull()
        ->and($businessProfile->status)->toBe(GrowthSyncTaskStatus::Success);

    $queue = GrowthSyncTask::query()->find(GrowthSyncTaskKey::OpportunityQueue->value);
    expect($queue)->not->toBeNull()
        ->and($queue->status)->toBe(GrowthSyncTaskStatus::Success);
});

it('queues publish maintenance when an opportunity is published', function (): void {
    Queue::fake();

    $opportunity = GrowthOpportunity::factory()->create([
        'status' => GrowthOpportunityStatus::Building,
    ]);

    GrowthOpportunityPublished::dispatch($opportunity);

    Queue::assertPushed(RunGrowthPublishMaintenanceJob::class);
});

it('queues content maintenance when opportunity content is updated', function (): void {
    Queue::fake();

    $opportunity = GrowthOpportunity::factory()->create([
        'status' => GrowthOpportunityStatus::Building,
    ]);

    GrowthOpportunityContentUpdated::dispatch($opportunity);

    Queue::assertPushed(RunGrowthContentMaintenanceJob::class);
});

it('records failure when search console returns no rows and adapter is unconfigured', function (): void {
    config([
        'growth.integrations.google_search_console.fixture_enabled' => false,
        'growth.integrations.google_search_console.enabled' => false,
    ]);

    app(GrowthMaintenancePipeline::class)->syncSearchConsole(now()->subDay());

    $task = app(GrowthSyncTaskRecorder::class)->lastRun(GrowthSyncTaskKey::SearchConsole);

    expect($task)->not->toBeNull()
        ->and($task->status)->toBe(GrowthSyncTaskStatus::Skipped);
});

it('shows sync status on the opportunities surface', function (): void {
    app(GrowthMaintenancePipeline::class)->runNightly();

    $admin = User::factory()->create();
    $admin->assignRole(ArkRole::Admin->value);

    $this->actingAs($admin)
        ->withSession([WorkstationPresence::SESSION_BIND_DISMISSED => true])
        ->get(route('growth.opportunities.index'))
        ->assertOk()
        ->assertSee('Last synchronized', false)
        ->assertSee('Search Console', false)
        ->assertSee('Google Business Profile', false)
        ->assertSee('Opportunity Queue', false)
        ->assertDontSee('php artisan growth:sync-search-console', false);
});

it('queues rebuild from admin action', function (): void {
    Queue::fake();

    $admin = User::factory()->create();
    $admin->assignRole(ArkRole::Admin->value);

    $this->actingAs($admin)
        ->withSession([WorkstationPresence::SESSION_BIND_DISMISSED => true])
        ->post(route('growth.maintenance.rebuild'))
        ->assertRedirect();

    Queue::assertPushed(RunGrowthNightlyMaintenanceJob::class);
});

it('executes nightly maintenance job end to end', function (): void {
    (new RunGrowthNightlyMaintenanceJob)->handle(app(GrowthMaintenancePipeline::class));

    expect(GrowthSearchQuery::query()->count())->toBeGreaterThan(0);
});
