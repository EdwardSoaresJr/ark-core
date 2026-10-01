<?php

use App\Ark\Operations\Events\OperationalEvent;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\WorkAuthorization\AuthorizeTestingPackageAction;
use App\Ark\Operations\WorkAuthorization\RecordTestingPackageOutcomeAction;
use App\Ark\Operations\WorkAuthorization\TestingPackageOutcome;
use App\Ark\Operations\WorkAuthorization\WorkAuthorization;
use App\Ark\Operations\WorkAuthorization\WorkAuthorizationPackageType;
use App\Ark\Operations\WorkAuthorization\WorkAuthorizationStatus;
use App\Ark\Runtime\Authorization\ArkRole;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;
use Database\Seeders\ShopSettingsSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
    $this->seed(ShopSettingsSeeder::class);
});

test('authorize testing package creates concern repair action and package line without flag hours', function () {
    $advisor = actingAsLearnCurrentStaff(ArkRole::Advisor);
    $repairOrder = repairOrderForEstimateWorkspace();

    $authorization = app(AuthorizeTestingPackageAction::class)->handle($repairOrder, $advisor);

    expect($authorization->package_type)->toBe(WorkAuthorizationPackageType::Testing)
        ->and($authorization->status)->toBe(WorkAuthorizationStatus::Authorized)
        ->and($authorization->outcome)->toBeNull()
        ->and($authorization->concern)->not->toBeNull()
        ->and($authorization->workGroup)->not->toBeNull()
        ->and($authorization->packageLine?->type)->toBe(RepairOrderLineType::Package)
        ->and($authorization->packageLine?->type->countsTowardFlagHours())->toBeFalse()
        ->and((int) $authorization->packageLine?->unit_price_cents)->toBe(0);

    $totals = app(EstimateTotalsCalculator::class)->totalsFor($repairOrder->fresh(['lines.concern']));

    expect($totals->grossLaborCents())->toBe(0)
        ->and(OperationalEvent::query()
            ->where('event_name', OperationalEventName::WorkAuthorizationCreated->value)
            ->exists())->toBeTrue();
});

test('multiple testing packages can be authorized on one repair order', function () {
    $advisor = actingAsLearnCurrentStaff(ArkRole::Advisor);
    $repairOrder = repairOrderForEstimateWorkspace();

    app(AuthorizeTestingPackageAction::class)->handle($repairOrder, $advisor);
    app(AuthorizeTestingPackageAction::class)->handle($repairOrder->fresh(), $advisor);

    expect(WorkAuthorization::query()->where('repair_order_id', $repairOrder->id)->count())->toBe(2);
});

test('recording testing outcome completes authorization with recommendation when required', function () {
    $advisor = actingAsLearnCurrentStaff(ArkRole::Advisor);
    $repairOrder = repairOrderForEstimateWorkspace();
    $authorization = app(AuthorizeTestingPackageAction::class)->handle($repairOrder, $advisor);

    $completed = app(RecordTestingPackageOutcomeAction::class)->handle(
        $authorization,
        $advisor,
        TestingPackageOutcome::RepairRecommended,
        'Replace starter solenoid.',
    );

    expect($completed->status)->toBe(WorkAuthorizationStatus::Completed)
        ->and($completed->outcome)->toBe(TestingPackageOutcome::RepairRecommended)
        ->and($completed->recommendation)->toBe('Replace starter solenoid.')
        ->and(OperationalEvent::query()
            ->where('event_name', OperationalEventName::WorkAuthorizationCompleted->value)
            ->exists())->toBeTrue();
});

test('repair recommended without recommendation is rejected', function () {
    $advisor = actingAsLearnCurrentStaff(ArkRole::Advisor);
    $repairOrder = repairOrderForEstimateWorkspace();
    $authorization = app(AuthorizeTestingPackageAction::class)->handle($repairOrder, $advisor);

    app(RecordTestingPackageOutcomeAction::class)->handle(
        $authorization,
        $advisor,
        TestingPackageOutcome::EscalateTesting,
        null,
    );
})->throws(Illuminate\Validation\ValidationException::class);

test('advisor can authorize testing package and record outcome via http', function () {
    $advisor = actingAsLearnCurrentStaff(ArkRole::Advisor);
    $repairOrder = repairOrderForEstimateWorkspace();

    $this->actingAs($advisor)
        ->post(route('operations.repair-orders.work-authorization.testing.store', $repairOrder), [
            App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD => $repairOrder->estimate_version ?? 1,
        ])
        ->assertRedirect();

    $authorization = WorkAuthorization::query()->where('repair_order_id', $repairOrder->id)->sole();

    $this->actingAs($advisor)
        ->get(route('operations.repair-orders.work-authorization.outcome', [$repairOrder, $authorization]))
        ->assertOk()
        ->assertSee('Record Testing Outcome');

    $this->actingAs($advisor)
        ->post(route('operations.repair-orders.work-authorization.outcome.store', [$repairOrder, $authorization]), [
            'outcome' => TestingPackageOutcome::NoFaultFound->value,
        ])
        ->assertRedirect();

    expect($authorization->fresh()->outcome)->toBe(TestingPackageOutcome::NoFaultFound)
        ->and($authorization->fresh()->status)->toBe(WorkAuthorizationStatus::Completed);

    $this->actingAs($advisor)
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Work Authorization')
        ->assertSee('Testing Package')
        ->assertSee('No fault found');
});
