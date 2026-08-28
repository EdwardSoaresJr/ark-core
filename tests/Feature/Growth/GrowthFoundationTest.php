<?php

use App\Ark\Growth\Contracts\Operations\LeadConvertedForGrowth;
use App\Ark\Growth\Contracts\Operations\LeadConvertedPayload;
use App\Ark\Growth\Contracts\Operations\PublicSurfaceActivityPayload;
use App\Ark\Growth\Contracts\Operations\PublicSurfaceActivityRecorded;
use App\Ark\Growth\Contracts\Operations\RepairOrderClosedForGrowth;
use App\Ark\Growth\Contracts\Operations\RepairOrderClosedPayload;
use App\Ark\Growth\Models\GrowthAttribution;
use App\Ark\Growth\Models\GrowthContent;
use App\Ark\Growth\Models\GrowthEvent;
use App\Ark\Growth\Models\GrowthLastTouch;
use App\Ark\Growth\Models\GrowthSession;
use App\Ark\Growth\Models\GrowthTouchpoint;
use App\Ark\Growth\Redirects\RedirectResolver;
use App\Ark\Growth\Seo\SchemaRegistry;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Leads\Lead;
use App\Ark\Operations\Leads\Public\PublicSurfaceEventType;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
});

it('restricts growth dashboard to growth access capability', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(ArkRole::Admin->value);

    $advisor = User::factory()->create();
    $advisor->assignRole(ArkRole::Advisor->value);

    $this->actingAs($admin)
        ->withSession([\App\Ark\Operations\Workstations\WorkstationPresence::SESSION_BIND_DISMISSED => true])
        ->get(route('growth.dashboard'))
        ->assertOk();
    $this->actingAs($advisor)->get(route('growth.dashboard'))->assertForbidden();
});

it('registers schema types without modifying core registry code', function (): void {
    $registry = app(SchemaRegistry::class);

    expect($registry->types())->toContain('AutoRepair', 'FAQPage', 'Vehicle');

    $schema = $registry->build('Service', [
        'name' => 'Brake Service',
        'url' => 'https://example.test/brakes',
    ]);

    expect($schema['@type'])->toBe('Service')
        ->and($schema['name'])->toBe('Brake Service');
});

it('records revenue attribution from contract event with growth session', function (): void {
    $session = GrowthSession::query()->create([
        'visitor_id' => (string) \Illuminate\Support\Str::uuid(),
        'laravel_session_id' => 'test-session-1',
        'started_at' => now(),
        'first_landing_page' => '/services/brakes',
        'first_search_query' => 'brake repair',
        'first_campaign' => 'spring-brakes',
    ]);

    GrowthContent::query()->create([
        'slug' => 'brakes',
        'template' => 'services',
        'title' => 'Brake Service',
        'path' => '/services/brakes',
        'published_at' => now(),
        'indexable' => true,
        'priority' => 80,
    ]);

    RepairOrderClosedForGrowth::dispatch(new RepairOrderClosedPayload(
        repairOrderId: 0,
        revenueCents: 125_000,
        growthSessionId: $session->id,
        landingPage: '/services/brakes',
        searchQuery: 'brake repair',
        source: 'organic',
        campaign: 'spring-brakes',
    ));

    $attribution = GrowthAttribution::query()->first();
    expect($attribution)->not->toBeNull()
        ->and($attribution->growth_session_id)->toBe($session->id)
        ->and($attribution->revenue_cents)->toBe(125_000);

    $content = GrowthContent::query()->where('slug', 'brakes')->first();
    expect($content->revenue_cents)->toBe(125_000);
});

it('bridges public surface activity into growth session touchpoints', function (): void {
    PublicSurfaceActivityRecorded::dispatch(new PublicSurfaceActivityPayload(
        laravelSessionId: 'laravel-session-abc',
        surfaceEventType: PublicSurfaceEventType::SurfaceViewed->value,
        context: ['page' => 'common-problems.brake-noise'],
        requestMeta: [
            'landing_page' => '/common-problems/brake-noise',
            'referrer' => 'https://www.google.com/search?q=brake+noise',
            'search_query' => 'brake noise',
            'utm_campaign' => 'organic-search',
            'device' => 'mobile',
        ],
        visitorId: (string) \Illuminate\Support\Str::uuid(),
    ));

    $session = GrowthSession::query()->where('laravel_session_id', 'laravel-session-abc')->first();

    expect($session)->not->toBeNull()
        ->and($session->first_landing_page)->toBe('/common-problems/brake-noise')
        ->and($session->first_search_query)->toBe('brake noise')
        ->and(GrowthTouchpoint::query()->where('growth_session_id', $session->id)->count())->toBe(1)
        ->and(GrowthEvent::query()->where('growth_session_id', $session->id)->count())->toBe(1)
        ->and(GrowthLastTouch::query()->where('growth_session_id', $session->id)->exists())->toBeTrue();
});

it('links growth session to lead and repair order on conversion', function (): void {
    $session = GrowthSession::query()->create([
        'visitor_id' => (string) \Illuminate\Support\Str::uuid(),
        'laravel_session_id' => 'convert-session',
        'started_at' => now(),
        'first_landing_page' => '/',
    ]);

    $lead = Lead::query()->create([
        'uuid' => (string) \Illuminate\Support\Str::uuid(),
        'source' => 'website',
        'state' => 'received',
        'concern' => 'Brakes',
        'contact_phone' => '+17195551212',
        'growth_session_id' => $session->id,
    ]);

    $customer = Customer::query()->create([
        'first_name' => 'Growth',
        'last_name' => 'Test',
        'phone' => '555-0100',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2018,
        'make' => 'Honda',
        'model' => 'Accord',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Brake work',
    ]);

    LeadConvertedForGrowth::dispatch(new LeadConvertedPayload(
        leadId: $lead->id,
        repairOrderId: $repairOrder->id,
    ));

    expect($repairOrder->fresh()->growth_session_id)->toBe($session->id);
});

it('prevents redirect loops', function (): void {
    \App\Ark\Growth\Models\GrowthRedirect::query()->create([
        'from_path' => '/a',
        'to_path' => '/b',
        'status_code' => 301,
        'is_active' => true,
    ]);

    \App\Ark\Growth\Models\GrowthRedirect::query()->create([
        'from_path' => '/b',
        'to_path' => '/a',
        'status_code' => 301,
        'is_active' => true,
    ]);

    $resolver = app(RedirectResolver::class);
    $candidate = new \App\Ark\Growth\Models\GrowthRedirect([
        'from_path' => '/c',
        'to_path' => '/a',
        'status_code' => 301,
    ]);

    expect($resolver->wouldLoop($candidate, '/a'))->toBeTrue();
});

it('renders revenue explorer for admins', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(ArkRole::Admin->value);

    $this->actingAs($admin)
        ->withSession([\App\Ark\Operations\Workstations\WorkstationPresence::SESSION_BIND_DISMISSED => true])
        ->get(route('growth.revenue-explorer', ['q' => 'high_revenue_pages']))
        ->assertOk()
        ->assertSee('Revenue Explorer');
});

it('records server-side public page views into growth sessions', function (): void {
    GrowthContent::query()->create([
        'slug' => 'home',
        'template' => 'pages',
        'title' => 'Home',
        'path' => '/',
        'published_at' => now(),
        'indexable' => true,
        'priority' => 100,
    ]);

    $this->get(route('public.home'))
        ->assertOk();

    $session = GrowthSession::query()->first();

    expect($session)->not->toBeNull()
        ->and($session->first_landing_page)->toBe('/')
        ->and($session->first_growth_content_id)->not->toBeNull()
        ->and(GrowthTouchpoint::query()->where('type', 'page_viewed')->count())->toBe(1);
});

it('renders landing session debugger for admins', function (): void {
    GrowthSession::query()->create([
        'visitor_id' => (string) \Illuminate\Support\Str::uuid(),
        'laravel_session_id' => 'debug-session',
        'started_at' => now(),
        'first_landing_page' => '/common-problems/brake-noise',
    ]);

    $admin = User::factory()->create();
    $admin->assignRole(ArkRole::Admin->value);

    $this->actingAs($admin)
        ->withSession([\App\Ark\Operations\Workstations\WorkstationPresence::SESSION_BIND_DISMISSED => true])
        ->get(route('growth.sessions.index'))
        ->assertOk()
        ->assertSee('Landing sessions')
        ->assertSee('/common-problems/brake-noise');
});
