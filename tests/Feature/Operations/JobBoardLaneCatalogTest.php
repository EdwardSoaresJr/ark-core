<?php

use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusCatalog;
use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusColor;
use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusDefinition;
use App\Ark\Operations\Workboard\JobBoardLane;
use App\Ark\Operations\Workboard\JobBoardLaneCatalog;
use App\Ark\Operations\Workboard\JobBoardLaneCatalogDefaults;
use App\Ark\Operations\Workboard\WorkboardCardGlance;
use App\Ark\Operations\Workboard\WorkboardCardGlanceProjection;
use App\Ark\Operations\Workboard\WorkboardSwimlaneCatalog;
use App\Ark\Operations\Workboard\WorkboardTriageCard;
use App\Ark\Runtime\Authorization\ArkRole;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
});

test('job board ships the five default lanes in shop order', function () {
    $columns = app(JobBoardLaneCatalog::class)->homeBoardColumns();

    expect(collect($columns)->pluck('key')->all())->toBe([
        JobBoardLaneCatalogDefaults::ESTIMATES,
        JobBoardLaneCatalogDefaults::WAITING_APPROVAL,
        JobBoardLaneCatalogDefaults::PARTS,
        JobBoardLaneCatalogDefaults::WORK_IN_PROGRESS,
        JobBoardLaneCatalogDefaults::COMPLETED,
    ])->and(collect($columns)->pluck('label')->all())->toBe([
        'Estimates',
        'Waiting Approval',
        'Waiting Parts',
        'Work in Progress',
        'Completed',
    ]);
});

test('lane color persists independently from status color', function () {
    $admin = actingAsLearnCurrentStaff(ArkRole::Admin);

    $this->actingAs($admin)
        ->patch(route('operations.settings.shop.job-board-lanes.update'), [
            'lanes' => [
                JobBoardLaneCatalogDefaults::PARTS => [
                    'name' => 'Waiting Parts',
                    'color' => RepairOrderStatusColor::PRIMARY,
                    'sort_order' => 2,
                    'active' => '1',
                ],
            ],
        ])
        ->assertRedirect();

    app(JobBoardLaneCatalog::class)->forgetCache();

    $partsLane = app(JobBoardLaneCatalog::class)->lane(JobBoardLaneCatalogDefaults::PARTS);
    $waitingParts = RepairOrderStatusDefinition::query()->where('slug', 'waiting_parts')->first();

    expect($partsLane['color'])->toBe(RepairOrderStatusColor::PRIMARY)
        ->and($waitingParts?->color)->toBe(RepairOrderStatusColor::INFO);
});

test('status color persists and statuses belong to their configured lane', function () {
    $admin = actingAsLearnCurrentStaff(ArkRole::Admin);

    $this->actingAs($admin)
        ->patch(route('operations.settings.shop.status-catalog.update'), [
            'statuses' => [
                'quality_check' => [
                    'name' => 'Quality Check',
                    'color' => RepairOrderStatusColor::SUCCESS,
                    'advisor_lane_key' => JobBoardLaneCatalogDefaults::WORK_IN_PROGRESS,
                    'sort_order' => 6,
                    'show_on_advisor_board' => '1',
                ],
            ],
        ])
        ->assertRedirect();

    app(RepairOrderStatusCatalog::class)->forgetCache();

    $qualityCheck = RepairOrderStatusDefinition::query()->where('slug', 'quality_check')->first();

    expect($qualityCheck?->color)->toBe(RepairOrderStatusColor::SUCCESS)
        ->and($qualityCheck?->advisor_lane_key)->toBe(JobBoardLaneCatalogDefaults::WORK_IN_PROGRESS)
        ->and($qualityCheck?->sort_order)->toBe(6);
});

test('invalid lane and status combinations are rejected', function () {
    $admin = actingAsLearnCurrentStaff(ArkRole::Admin);

    $this->actingAs($admin)
        ->from(route('operations.settings.shop.edit', ['section' => 'workflow', 'workflow-tab' => 'statuses']))
        ->patch(route('operations.settings.shop.status-catalog.update'), [
            'statuses' => [
                'quality_check' => [
                    'advisor_lane_key' => 'not_a_real_lane',
                ],
            ],
        ])
        ->assertSessionHasErrors('statuses.quality_check.advisor_lane_key');

    expect(RepairOrderStatusDefinition::query()->where('slug', 'quality_check')->value('advisor_lane_key'))
        ->toBe(JobBoardLaneCatalogDefaults::WORK_IN_PROGRESS);
});

test('terminal means off the job board not the completed lane', function () {
    $catalog = app(RepairOrderStatusCatalog::class);
    $closed = $catalog->definitionForSlug('closed');
    $completed = $catalog->definitionForSlug('completed');
    $readyPickup = $catalog->definitionForSlug('ready_pickup');

    expect($closed?->is_terminal)->toBeTrue()
        ->and($closed?->show_on_advisor_board)->toBeFalse()
        ->and($closed?->advisor_lane_key)->toBeNull()
        ->and($completed?->is_terminal)->toBeFalse()
        ->and($completed?->advisor_lane_key)->toBe(JobBoardLaneCatalogDefaults::COMPLETED)
        ->and($readyPickup?->is_terminal)->toBeFalse()
        ->and($readyPickup?->advisor_lane_key)->toBe(JobBoardLaneCatalogDefaults::COMPLETED);
});

test('existing repair orders map into the default five lanes', function () {
    $draft = decisionPressureRepairOrder('Draft', 'Lane', RepairOrderStatus::Draft, 10_000);
    $quality = decisionPressureRepairOrder('QC', 'Lane', RepairOrderStatus::QualityCheck, 20_000);
    $pickup = decisionPressureRepairOrder('Pickup', 'Lane', RepairOrderStatus::ReadyPickup, 30_000);

    expect(WorkboardSwimlaneCatalog::homeBoardColumnKeyForRepairOrder($draft->fresh()))
        ->toBe(JobBoardLaneCatalogDefaults::ESTIMATES)
        ->and(WorkboardSwimlaneCatalog::homeBoardColumnKeyForRepairOrder($quality->fresh()))
        ->toBe(JobBoardLaneCatalogDefaults::WORK_IN_PROGRESS)
        ->and(WorkboardSwimlaneCatalog::homeBoardColumnKeyForRepairOrder($pickup->fresh()))
        ->toBe(JobBoardLaneCatalogDefaults::COMPLETED);
});

test('changing status displays the card in the status lane', function () {
    $repairOrder = decisionPressureRepairOrder(
        'Move',
        'Card',
        RepairOrderStatus::Approved,
        90_000,
    );

    expect(WorkboardSwimlaneCatalog::homeBoardColumnKeyForRepairOrder($repairOrder))
        ->toBe(JobBoardLaneCatalogDefaults::WORK_IN_PROGRESS);

    $advisor = actingAsLearnCurrentAdvisor();

    $this->actingAs($advisor)
        ->from(route('operations.index'))
        ->patch(route('operations.repair-orders.lifecycle.update', $repairOrder), [
            'status' => RepairOrderStatus::WaitingParts->value,
        ])
        ->assertRedirect();

    expect(WorkboardSwimlaneCatalog::homeBoardColumnKeyForRepairOrder($repairOrder->fresh()))
        ->toBe(JobBoardLaneCatalogDefaults::PARTS);
});

test('attention stays independent from configured lane and status color', function () {
    $repairOrder = decisionPressureRepairOrder(
        'Calm',
        'Color',
        RepairOrderStatus::InProgress,
        80_000,
    );

    JobBoardLane::query()->where('key', JobBoardLaneCatalogDefaults::WORK_IN_PROGRESS)->update([
        'color' => RepairOrderStatusColor::WARNING,
    ]);
    RepairOrderStatusDefinition::query()->where('slug', 'in_progress')->update([
        'color' => RepairOrderStatusColor::WARNING,
    ]);
    app(JobBoardLaneCatalog::class)->forgetCache();
    app(RepairOrderStatusCatalog::class)->forgetCache();

    $repairOrder = $repairOrder->fresh(['customer', 'vehicle', 'lines', 'communicationEvents']);
    $repairOrder->setRelation('communicationEvents', collect());

    $card = new WorkboardTriageCard(
        repairOrder: $repairOrder,
        vehicleLabel: '2018 Subaru Outback',
        concernSummary: 'Decision pressure coverage.',
        concernHeadline: 'Decision pressure coverage',
        signalLabel: null,
        signalTone: 'neutral',
        ageLabel: '2h',
        ageMinutes: 120,
        pressureScore: 0,
        countsAsNeedsAttention: false,
        countsAsCustomerWaiting: false,
        countsAsUnassigned: false,
        countsAsOverduePickup: false,
        href: '/app/repair-orders/1',
    );

    $glance = (new WorkboardCardGlanceProjection)->forCard(
        $card,
        JobBoardLaneCatalogDefaults::WORK_IN_PROGRESS,
        null,
        null,
        now()->subHours(2),
        false,
    );

    expect($glance->attention)->toBe(WorkboardCardGlance::ATTENTION_NORMAL)
        ->and($glance->configuredStatusColor)->toBe(RepairOrderStatusColor::WARNING)
        ->and(app(JobBoardLaneCatalog::class)->colorForKey(JobBoardLaneCatalogDefaults::WORK_IN_PROGRESS))
        ->toBe(RepairOrderStatusColor::WARNING);
});

test('job board settings can rename a default lane without changing its key', function () {
    $admin = actingAsLearnCurrentStaff(ArkRole::Admin);

    $this->actingAs($admin)
        ->patch(route('operations.settings.shop.job-board-lanes.update'), [
            'lanes' => [
                JobBoardLaneCatalogDefaults::PARTS => [
                    'name' => 'Parts Hold',
                    'color' => RepairOrderStatusColor::INFO,
                    'sort_order' => 2,
                    'active' => '1',
                ],
            ],
        ])
        ->assertRedirect();

    app(JobBoardLaneCatalog::class)->forgetCache();

    expect(app(JobBoardLaneCatalog::class)->labelForKey(JobBoardLaneCatalogDefaults::PARTS))->toBe('Parts Hold');

    $this->actingAs($admin)
        ->get(route('operations.settings.shop.edit', ['section' => 'workflow', 'workflow-tab' => 'statuses']))
        ->assertOk()
        ->assertSee('Job Board lanes', false)
        ->assertSee('Parts Hold', false);
});
