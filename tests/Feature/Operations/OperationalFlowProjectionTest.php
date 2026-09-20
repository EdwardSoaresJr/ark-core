<?php

use App\Ark\Operations\Events\OperationalEvent;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Events\OperationalEventRecorder;
use App\Ark\Operations\Flow\FlowStageKey;
use App\Ark\Operations\Flow\OperationalFlowProjectionBuilder;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Workboard\WorkboardTriageRepairOrderQuery;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
});

test('flow assigns pre-production draft without lines to work arrives', function () {
    $repairOrder = flowRepairOrderWithoutLines(RepairOrderStatus::Draft);

    $projection = app(OperationalFlowProjectionBuilder::class)->build(collect([$repairOrder]));
    $stage = collect($projection->stages)->firstWhere(
        fn ($stage) => $stage->stageKey === FlowStageKey::WorkArrives,
    );

    expect($stage)->not->toBeNull()
        ->and($stage->count)->toBe(1);

    $needsDiagnosis = collect($projection->stages)->firstWhere(
        fn ($stage) => $stage->stageKey === FlowStageKey::NeedsDiagnosis,
    );

    expect($needsDiagnosis->count)->toBe(0);
});

test('flow assigns draft with lines to needs diagnosis not work arrives', function () {
    $repairOrder = decisionPressureRepairOrder(
        firstName: 'Diag',
        lastName: 'Queue',
        status: RepairOrderStatus::Draft,
        lineCents: 45_000,
    );

    $projection = app(OperationalFlowProjectionBuilder::class)->build(collect([$repairOrder]));

    expect(collect($projection->stages)->firstWhere(
        fn ($stage) => $stage->stageKey === FlowStageKey::NeedsDiagnosis,
    )->count)->toBe(1)
        ->and(collect($projection->stages)->firstWhere(
            fn ($stage) => $stage->stageKey === FlowStageKey::WorkArrives,
        )->count)->toBe(0);
});

test('flow excludes paid and closed stages from v1 model', function () {
    $projection = app(OperationalFlowProjectionBuilder::class)->build(collect());

    expect(collect($projection->stages)->pluck('stageKey')->all())->toBe(FlowStageKey::ordered())
        ->and(collect($projection->stages)->contains(fn ($stage) => $stage->stageKey->value === 'paid'))->toBeFalse()
        ->and($projection->constraint)->toBeNull();
});

test('flow constraint favors waiting approval when revenue volume and age dominate', function () {
    Carbon::setTestNow('2026-06-15 12:00:00');

    $waitingLarge = decisionPressureRepairOrder(
        firstName: 'Wait',
        lastName: 'Large',
        status: RepairOrderStatus::WaitingApproval,
        lineCents: 450_000,
    );
    $waitingSmall = decisionPressureRepairOrder(
        firstName: 'Wait',
        lastName: 'Small',
        status: RepairOrderStatus::WaitingApproval,
        lineCents: 380_000,
    );
    $inRepair = decisionPressureRepairOrder(
        firstName: 'Floor',
        lastName: 'Tech',
        status: RepairOrderStatus::InProgress,
        lineCents: 25_000,
    );

    foreach ([$waitingLarge, $waitingSmall] as $repairOrder) {
        $repairOrder->forceFill(['updated_at' => now()->subDays(4)])->save();
    }

    $inRepair->forceFill(['updated_at' => now()->subHours(6)])->save();

    $projection = app(OperationalFlowProjectionBuilder::class)->build(collect([
        $waitingLarge,
        $waitingSmall,
        $inRepair,
    ]));

    expect($projection->constraint)->not->toBeNull()
        ->and($projection->constraint->stageKey)->toBe(FlowStageKey::WaitingApproval)
        ->and($projection->constraint->reasons)->not->toBeEmpty();

    $waitingStage = collect($projection->stages)->firstWhere(
        fn ($stage) => $stage->stageKey === FlowStageKey::WaitingApproval,
    );

    expect($waitingStage->count)->toBe(2)
        ->and($waitingStage->revenueCents)->toBeGreaterThan(800_000);

    Carbon::setTestNow();
});

test('flow uses latest lifecycle transition for time in stage', function () {
    Carbon::setTestNow('2026-06-15 12:00:00');

    $repairOrder = decisionPressureRepairOrder(
        firstName: 'Lifecycle',
        lastName: 'Age',
        status: RepairOrderStatus::WaitingApproval,
        lineCents: 120_000,
    );

    OperationalEvent::query()->create([
        'event_name' => OperationalEventName::RepairOrderLifecycleChanged->value,
        'aggregate_type' => RepairOrder::class,
        'aggregate_id' => $repairOrder->id,
        'occurred_at' => now()->subDays(3),
        'payload_json' => [
            'from_status' => RepairOrderStatus::Estimate->value,
            'to_status' => RepairOrderStatus::WaitingApproval->value,
        ],
    ]);

    $repairOrder->forceFill(['updated_at' => now()->subHour()])->save();

    $projection = app(OperationalFlowProjectionBuilder::class)->build(collect([$repairOrder->fresh(['lines.concern'])]));
    $waitingStage = collect($projection->stages)->firstWhere(
        fn ($stage) => $stage->stageKey === FlowStageKey::WaitingApproval,
    );

    expect($waitingStage->medianAgeMinutes)->toBeGreaterThanOrEqual(3 * 24 * 60 - 5)
        ->and($waitingStage->oldestAgeLabel)->toBe('3d');

    Carbon::setTestNow();
});

test('advisor triage cohort produces stable flow projection', function () {
    $repairOrder = decisionPressureRepairOrder(
        firstName: 'Cohort',
        lastName: 'One',
        status: RepairOrderStatus::WaitingApproval,
        lineCents: 90_000,
    );

    $repairOrders = app(WorkboardTriageRepairOrderQuery::class)->forAdvisor();

    expect($repairOrders->pluck('id'))->toContain($repairOrder->id);

    $projection = app(OperationalFlowProjectionBuilder::class)->build($repairOrders);

    expect($projection->generatedAt)->not->toBeNull()
        ->and($projection->stages)->toHaveCount(count(FlowStageKey::ordered()))
        ->and(collect($projection->stages)->sum('count'))->toBeGreaterThan(0);
});

function flowRepairOrderWithoutLines(RepairOrderStatus $status): RepairOrder
{
    $customer = \App\Ark\Operations\Customers\Customer::query()->create([
        'first_name' => 'Intake',
        'last_name' => 'Only',
        'phone' => '555-0199',
    ]);

    $vehicle = \App\Ark\Operations\Vehicles\Vehicle::query()->create([
        'customer_id' => $customer->id,
        'plate' => 'INT'.$customer->id,
        'year' => 2019,
        'make' => 'Toyota',
        'model' => 'Camry',
    ]);

    return RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => $status,
        'concern_summary' => 'Scheduled intake — not yet diagnosed.',
        'opened_at' => now(),
    ]);
}
