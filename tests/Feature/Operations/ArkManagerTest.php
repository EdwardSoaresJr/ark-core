<?php

use App\Ark\Operations\ArkManager\ArkManagerContextBuilder;
use App\Ark\Operations\ArkManager\ArkManagerService;
use App\Ark\Operations\ArkManager\DeterministicAiManagerProvider;
use App\Ark\Operations\Communications\CommunicationEvent;
use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Communications\OperationalCommunicationType;
use App\Ark\Operations\Flow\OperationalFlowProjectionBuilder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Today\AdvisorTodayProjection;
use App\Ark\Operations\Today\AdvisorTodayRecommendationEngine;
use App\Ark\Operations\Today\AdvisorTodayShopRadarBuilder;
use App\Ark\Operations\Today\TodayCommitmentsProjection;
use App\Ark\Operations\Today\TodayPipelineProjection;
use App\Ark\Operations\Workboard\WorkboardTriageRepairOrderQuery;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
    Cache::flush();
});

function resolveAdvisorTodayProjection(): AdvisorTodayProjection
{
    return AdvisorTodayProjection::resolve(
        app(WorkboardTriageRepairOrderQuery::class),
        app(AdvisorTodayRecommendationEngine::class),
        app(AdvisorTodayShopRadarBuilder::class),
        app(TodayPipelineProjection::class),
        app(TodayCommitmentsProjection::class),
        app(OperationalFlowProjectionBuilder::class),
        auth()->user(),
    );
}

test('deterministic ark manager morning brief explains operational context without openai', function () {
    Carbon::setTestNow('2026-06-15 08:00:00');

    decisionPressureRepairOrder(
        firstName: 'Jane',
        lastName: 'Doe',
        status: RepairOrderStatus::WaitingApproval,
        lineCents: 420_000,
    );

    $advisor = actingAsLearnCurrentAdvisor();
    $today = resolveAdvisorTodayProjection();
    $brief = app(ArkManagerService::class)->morningBrief($today, $advisor);

    expect($brief->aiEnhanced)->toBeFalse()
        ->and($brief->source)->toBe('deterministic')
        ->and($brief->body())->toContain('Good morning')
        ->and($brief->recommendedFocus)->toBe('Approval follow-up');

    Carbon::setTestNow();
});

test('morning brief rebuilds when cache holds a stale serialized object', function () {
    Carbon::setTestNow('2026-06-15 08:00:00');

    decisionPressureRepairOrder(
        firstName: 'Jane',
        lastName: 'Doe',
        status: RepairOrderStatus::WaitingApproval,
        lineCents: 420_000,
    );

    $advisor = actingAsLearnCurrentAdvisor();
    $today = resolveAdvisorTodayProjection();
    $context = app(ArkManagerContextBuilder::class)->fromToday($today);

    Cache::put(
        'ark-manager:v1:brief:'.$context->shopDate.':'.$advisor->id,
        unserialize('O:8:"stdClass":0:{}'),
        now()->addHour(),
    );

    $brief = app(ArkManagerService::class)->morningBrief($today, $advisor);

    expect($brief)->toBeInstanceOf(\App\Ark\Operations\ArkManager\ArkManagerMorningBrief::class)
        ->and($brief->body())->toContain('Good morning');

    Carbon::setTestNow();
});

test('work page renders ark manager brief instead of coming soon', function () {
    Carbon::setTestNow('2026-06-15 08:00:00');

    decisionPressureRepairOrder(
        firstName: 'Jane',
        lastName: 'Doe',
        status: RepairOrderStatus::WaitingApproval,
        lineCents: 420_000,
    );

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->get(route('operations.index'))
        ->assertOk()
        ->assertSee('ARK Manager brief')
        ->assertSee('Read-only brief')
        ->assertSee('Good morning')
        ->assertSee('Focus')
        ->assertSee('Approval follow-up')
        ->assertDontSee('Coming soon');

    Carbon::setTestNow();
});

test('ark manager explain recommendation endpoint returns human explanation', function () {
    Carbon::setTestNow('2026-06-15 12:00:00');

    $advisor = actingAsLearnCurrentAdvisor();
    $repairOrder = repairOrderForCommunication(RepairOrderStatus::WaitingApproval, 'Ark Explain');
    $repairOrder->update(['concern_summary' => 'ARK-EXPLAIN-UNIQUE']);

    CommunicationEvent::query()->create([
        'repair_order_id' => $repairOrder->id,
        'event_type' => OperationalCommunicationType::EstimateViewed,
        'channel' => OperationalCommunicationChannel::Website,
        'direction' => OperationalCommunicationDirection::Inbound,
        'summary' => 'Customer opened estimate portal',
        'occurred_at' => now()->subHour(),
    ]);

    $this->actingAs($advisor)
        ->postJson(route('operations.today.ark-manager.explain'), [
            'repair_order_id' => $repairOrder->repair_order_id,
        ])
        ->assertOk()
        ->assertJsonPath('ai_enhanced', false)
        ->assertJsonPath('source', 'deterministic')
        ->assertJsonStructure(['explanation']);

    Carbon::setTestNow();
});

test('ark manager draft communication endpoint returns draft requiring human approval', function () {
    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->postJson(route('operations.today.ark-manager.draft'), [
            'channel' => 'sms',
            'customer_name' => 'Jane Doe',
            'purpose' => 'approval_follow_up',
        ])
        ->assertOk()
        ->assertJsonPath('channel', 'sms')
        ->assertJsonPath('requires_human_approval', true)
        ->assertJsonPath('ai_enhanced', false)
        ->assertJsonStructure(['body']);
});

test('deterministic provider explains recommendation from why reasons', function () {
    actingAsLearnCurrentAdvisor();

    $provider = app(DeterministicAiManagerProvider::class);
    $context = app(ArkManagerContextBuilder::class)->fromToday(resolveAdvisorTodayProjection());

    $explanation = $provider->explainRecommendation($context, [
        'customer_name' => 'John Smith',
        'title' => 'Customer viewed estimate',
        'why_reasons' => [
            'Estimate viewed multiple times',
            'Customer waiting response',
        ],
        'impact_label' => '$2,350 waiting approval',
        'suggested_action' => 'Follow up with a call',
    ]);

    expect($explanation->explanation)
        ->toContain('John Smith')
        ->toContain('Estimate viewed multiple times')
        ->toContain('Follow up with a call');
});
