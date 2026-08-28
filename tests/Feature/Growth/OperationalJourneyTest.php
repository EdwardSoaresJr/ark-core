<?php

use App\Ark\Growth\Identity\IdentityConfidenceResolver;
use App\Ark\Growth\Journey\OperationalJourneyProjection;
use App\Ark\Growth\Models\GrowthAttribution;
use App\Ark\Growth\Models\GrowthContent;
use App\Ark\Growth\Models\GrowthSession;
use App\Ark\Growth\Models\GrowthTouchpoint;
use App\Ark\Growth\Projections\JourneyExplorerProjection;
use App\Ark\Growth\Sessions\GrowthTouchpointType;
use App\Ark\Operations\Approvals\ApprovalEvent;
use App\Ark\Operations\Approvals\ApprovalSource;
use App\Ark\Operations\Approvals\ApprovalType;
use App\Ark\Operations\Communications\CommunicationEvent;
use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Communications\OperationalCommunicationType;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Leads\Lead;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
});

it('composes operational journey milestones from growth and operations sources', function (): void {
    $content = GrowthContent::query()->create([
        'slug' => 'wheel-bearing-noise',
        'template' => 'common-problems',
        'title' => 'Wheel Bearing Noise',
        'path' => '/common-problems/wheel-bearing-noise',
        'published_at' => now()->subDay(),
        'indexable' => true,
        'priority' => 70,
    ]);

    $session = GrowthSession::query()->create([
        'visitor_id' => (string) \Illuminate\Support\Str::uuid(),
        'laravel_session_id' => 'journey-session-1',
        'started_at' => now()->subDays(3)->setTime(9, 14),
        'first_landing_page' => '/common-problems/wheel-bearing-noise',
        'first_referrer' => 'https://www.google.com/search?q=wheel+bearing+noise',
        'first_search_query' => 'wheel bearing noise',
        'first_growth_content_id' => $content->id,
        'utm_source' => 'google',
    ]);

    GrowthTouchpoint::query()->create([
        'growth_session_id' => $session->id,
        'growth_content_id' => $content->id,
        'type' => GrowthTouchpointType::CalculatorUsed,
        'path' => '/tools/repair-cost-calculator',
        'recorded_at' => now()->subDays(3)->setTime(9, 20),
    ]);

    GrowthTouchpoint::query()->create([
        'growth_session_id' => $session->id,
        'type' => GrowthTouchpointType::CallClicked,
        'recorded_at' => now()->subDays(3)->setTime(9, 25),
    ]);

    $customer = Customer::query()->create([
        'first_name' => 'Wheel',
        'last_name' => 'Customer',
        'phone' => '7194136227',
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2015,
        'make' => 'Subaru',
        'model' => 'Outback',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'growth_session_id' => $session->id,
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Completed,
        'concern_summary' => 'Wheel bearing noise',
        'posted_at' => now()->subDay(),
        'opened_at' => now()->subDays(2),
    ]);

    Lead::query()->create([
        'growth_session_id' => $session->id,
        'uuid' => (string) \Illuminate\Support\Str::uuid(),
        'source' => 'website',
        'state' => 'converted',
        'concern' => 'Wheel bearing noise',
        'contact_phone' => '7194136227',
        'customer_id' => $customer->id,
        'repair_order_id' => $repairOrder->repair_order_id,
        'converted_at' => now()->subDays(2),
    ]);

    CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CA123',
        'direction' => 'inbound',
        'from_number' => '+17194136227',
        'normalized_from' => '+17194136227',
        'to_number' => '+17195551234',
        'status' => 'completed',
        'customer_id' => $customer->id,
        'repair_order_id' => $repairOrder->repair_order_id,
        'started_at' => now()->subDays(3)->setTime(9, 30),
        'answered_at' => now()->subDays(3)->setTime(9, 31),
        'owned_by_user_id' => User::factory()->create(['name' => 'Ben'])->id,
    ]);

    foreach ([1, 2, 3] as $i) {
        CommunicationEvent::query()->create([
            'repair_order_id' => $repairOrder->repair_order_id,
            'event_type' => OperationalCommunicationType::EstimateViewed,
            'channel' => OperationalCommunicationChannel::Website,
            'direction' => OperationalCommunicationDirection::Inbound,
            'summary' => 'Customer opened the estimate portal link.',
            'occurred_at' => now()->subDays(2)->addMinutes($i * 5),
        ]);
    }

    ApprovalEvent::query()->create([
        'visit_id' => $repairOrder->repair_order_id,
        'estimate_snapshot_reference' => 'test-snapshot',
        'approval_type' => ApprovalType::Repair,
        'approved_amount_cents' => 124_800,
        'source' => ApprovalSource::Portal,
        'approved_by' => 'Customer',
        'approved_at' => now()->subDays(2)->addMinutes(14),
    ]);

    GrowthAttribution::query()->create([
        'growth_session_id' => $session->id,
        'repair_order_id' => $repairOrder->repair_order_id,
        'landing_page' => '/common-problems/wheel-bearing-noise',
        'search_query' => 'wheel bearing noise',
        'source' => 'organic',
        'revenue_cents' => 124_800,
    ]);

    $projection = app(OperationalJourneyProjection::class)->forRepairOrder($repairOrder->fresh());

    expect($projection->hasStory)->toBeTrue()
        ->and($projection->identityConfidence->score)->toBe(95)
        ->and($projection->identityConfidence->reason)->toBe('Matched incoming phone number')
        ->and($projection->identityConfidence->evidence['lead_id'] ?? null)->not->toBeNull()
        ->and(collect($projection->identityConfidence->signals)->contains(fn (array $s): bool => $s['label'] === 'Phone matched lead' && $s['satisfied']))->toBeTrue();

    $estimateMilestone = collect($projection->milestones)->firstWhere('key', 'estimate_viewed_aggregate');
    expect($estimateMilestone)->not->toBeNull()
        ->and($estimateMilestone->evidenceItems)->toHaveCount(3);

    $headlines = collect($projection->milestones)->pluck('headline')->all();

    expect($headlines)->toContain('Google Search')
        ->and($headlines)->toContain('Wheel Bearing Noise')
        ->and(collect($headlines)->first(fn (string $h): bool => str_contains($h, 'Viewed estimate')))->not->toBeNull();

    $estimateCard = collect($projection->summaryCards)->firstWhere('key', 'estimate');
    expect($estimateCard)->not->toBeNull()
        ->and($estimateCard['value'])->toContain('3');

    $session->refresh();
    expect($session->identity_confidence_score)->toBe(95)
        ->and($session->identity_confidence_evidence['phone'] ?? null)->toBe('7194136227');
});

it('resolves identity confidence with evidence for portal authentication metadata', function (): void {
    $session = GrowthSession::query()->create([
        'visitor_id' => (string) \Illuminate\Support\Str::uuid(),
        'laravel_session_id' => 'portal-session',
        'started_at' => now(),
    ]);

    $customer = Customer::query()->create([
        'first_name' => 'Portal',
        'last_name' => 'User',
        'phone' => '555-0101',
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2020,
        'make' => 'Toyota',
        'model' => 'Camry',
    ]);

    $session->forceFill(['metadata' => ['portal_customer_id' => $customer->id]])->save();

    $repairOrder = RepairOrder::query()->create([
        'growth_session_id' => $session->id,
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Draft,
        'concern_summary' => 'Portal test',
        'opened_at' => now(),
    ]);

    $confidence = app(IdentityConfidenceResolver::class)->resolve($session, $repairOrder);

    expect($confidence->score)->toBe(100)
        ->and($confidence->reason)->toBe('Portal authentication')
        ->and($confidence->evidence['customer_id'] ?? null)->toBe($customer->id);
});

it('exposes journey explorer queries to growth access users', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(ArkRole::Admin->value);

    $this->actingAs($admin)
        ->withSession([\App\Ark\Operations\Workstations\WorkstationPresence::SESSION_BIND_DISMISSED => true])
        ->get(route('growth.journey-explorer', ['q' => 'high_revenue_repairs', 'threshold' => 2500]))
        ->assertOk()
        ->assertSee('Journey Explorer');
});

it('answers high revenue journey explorer query', function (): void {
    $session = GrowthSession::query()->create([
        'visitor_id' => (string) \Illuminate\Support\Str::uuid(),
        'laravel_session_id' => 'explorer-session',
        'started_at' => now()->subWeek(),
    ]);

    $customer = Customer::query()->create([
        'first_name' => 'High',
        'last_name' => 'Revenue',
        'phone' => '555-0200',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2019,
        'make' => 'Ford',
        'model' => 'F-150',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Completed,
        'concern_summary' => 'Brake job',
        'posted_at' => now(),
    ]);

    GrowthAttribution::query()->create([
        'growth_session_id' => $session->id,
        'repair_order_id' => $repairOrder->id,
        'revenue_cents' => 300_000,
        'landing_page' => '/services/brakes',
    ]);

    $result = app(JourneyExplorerProjection::class)->resolve('high_revenue_repairs', ['threshold_cents' => 250_000]);

    expect($result['rows'])->not->toBeEmpty()
        ->and($result['rows'][0]['revenue'])->toBe('$3,000')
        ->and($result['rows'][0]['repair_order'])->toBe('#'.$repairOrder->repair_order_id);
});

it('answers first touch by concern journey explorer query without invalid concern column', function (): void {
    $session = GrowthSession::query()->create([
        'visitor_id' => (string) \Illuminate\Support\Str::uuid(),
        'laravel_session_id' => 'explorer-concern-session',
        'started_at' => now()->subWeek(),
        'first_landing_page' => '/services/brakes',
    ]);

    $customer = Customer::query()->create([
        'first_name' => 'Brake',
        'last_name' => 'Customer',
        'phone' => '555-0201',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2020,
        'make' => 'Toyota',
        'model' => 'Camry',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'growth_session_id' => $session->id,
        'status' => RepairOrderStatus::Completed,
        'concern_summary' => 'Brake noise',
        'posted_at' => now(),
    ]);

    RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Front brake squeal',
    ]);

    $result = app(JourneyExplorerProjection::class)->resolve('first_touch_by_concern', ['keyword' => '']);

    expect($result['rows'])->not->toBeEmpty();
});
