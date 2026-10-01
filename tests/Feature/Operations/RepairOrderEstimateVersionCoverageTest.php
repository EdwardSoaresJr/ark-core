<?php

use App\Ark\Operations\Approvals\ApprovalEvent;
use App\Ark\Operations\Approvals\ApprovalSource;
use App\Ark\Operations\Financial\FinancialSubmissionIntentGate;
use App\Ark\Operations\Financial\LedgerEntryType;
use App\Ark\Operations\Financial\PaymentMethod;
use App\Ark\Operations\Financial\RepairOrderLedgerEntry;
use App\Ark\Operations\Maintenance\CancelEngineOilServiceAction;
use App\Ark\Operations\Maintenance\MaintenanceService;
use App\Ark\Operations\RepairOrders\ConcernBillingPosture;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderConcurrency;
use App\Ark\Operations\RepairOrders\RepairOrderEstimateChanged;
use App\Ark\Operations\RepairOrders\RepairOrderFinancialChanged;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\WorkAuthorization\WorkAuthorization;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    config(['broadcasting.default' => 'log']);
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));
});

test('manual visit reason advances the estimate version once and broadcasts after commit', function () {
    $repairOrder = repairOrderForEstimateWorkspace();
    $before = (int) $repairOrder->estimate_version;
    $broadcastLevel = null;
    $broadcasts = 0;

    Event::listen(RepairOrderEstimateChanged::class, function (RepairOrderEstimateChanged $event) use (&$broadcastLevel, &$broadcasts, $before): void {
        $broadcasts++;
        $broadcastLevel = DB::transactionLevel();

        expect((int) $event->repairOrder->estimate_version)->toBe($before + 1)
            ->and($event->broadcastAs())->toBe('estimate.changed');
    });

    $this->patch(route('operations.repair-orders.visit-reason.update', $repairOrder), [
        'visit_reason' => 'Brakes grind on the first stop.',
        RepairOrderConcurrency::FIELD => $before,
    ])->assertRedirect();

    $repairOrder->refresh();

    expect($repairOrder->visit_reason)->toBe('Brakes grind on the first stop.')
        ->and((int) $repairOrder->estimate_version)->toBe($before + 1)
        ->and($broadcasts)->toBe(1)
        ->and($broadcastLevel)->toBe(DB::transactionLevel());

    $this->patch(route('operations.repair-orders.visit-reason.update', $repairOrder), [
        'visit_reason' => 'Brakes grind on the first stop.',
        RepairOrderConcurrency::FIELD => $repairOrder->estimate_version,
    ])->assertRedirect();

    expect((int) $repairOrder->fresh()->estimate_version)->toBe($before + 1)
        ->and($broadcasts)->toBe(1);
});

test('billing posture advances the estimate version once and broadcasts after commit', function () {
    $repairOrder = repairOrderForEstimateWorkspace();
    $concern = concernForEstimateWorkspace($repairOrder);
    $before = (int) $repairOrder->fresh()->estimate_version;
    $broadcastLevel = null;
    $broadcasts = 0;

    Event::listen(RepairOrderEstimateChanged::class, function (RepairOrderEstimateChanged $event) use (&$broadcastLevel, &$broadcasts, $before): void {
        $broadcasts++;
        $broadcastLevel = DB::transactionLevel();

        expect((int) $event->repairOrder->estimate_version)->toBe($before + 1);
    });

    $this->patch(route('operations.repair-orders.concerns.billing-posture', [$repairOrder, $concern]), [
        'billing_posture' => ConcernBillingPosture::WarrantyOther->value,
        RepairOrderConcurrency::FIELD => $before,
    ])->assertRedirect();

    $repairOrder->refresh();
    $concern->refresh();

    expect($concern->billing_posture)->toBe(ConcernBillingPosture::WarrantyOther)
        ->and((int) $repairOrder->estimate_version)->toBe($before + 1)
        ->and($broadcasts)->toBe(1)
        ->and($broadcastLevel)->toBe(DB::transactionLevel());
});

test('engine oil service creates estimate content and advances the version once', function () {
    $advisor = actingAsLearnCurrentStaff(ArkRole::Advisor);
    $this->actingAs($advisor);
    $repairOrder = repairOrderForEstimateWorkspace();
    $before = (int) $repairOrder->estimate_version;

    Event::fake([RepairOrderEstimateChanged::class]);

    $this->post(route('operations.repair-orders.maintenance.engine-oil.store', $repairOrder), [
        'reset_reminder' => '1',
        RepairOrderConcurrency::FIELD => $before,
    ])->assertRedirect()->assertSessionHas('status', 'Saved');

    $repairOrder->refresh();

    expect(MaintenanceService::query()->where('repair_order_id', $repairOrder->id)->count())->toBe(1)
        ->and(RepairOrderLine::query()
            ->where('repair_order_id', $repairOrder->id)
            ->where('type', RepairOrderLineType::Package->value)
            ->count())->toBe(1)
        ->and((int) $repairOrder->estimate_version)->toBe($before + 1);

    Event::assertDispatchedTimes(RepairOrderEstimateChanged::class, 1);

    $this->post(route('operations.repair-orders.maintenance.engine-oil.store', $repairOrder), [
        'reset_reminder' => '1',
        RepairOrderConcurrency::FIELD => $repairOrder->estimate_version,
    ])->assertRedirect();

    expect((int) $repairOrder->fresh()->estimate_version)->toBe($before + 1)
        ->and(RepairOrderLine::query()
            ->where('repair_order_id', $repairOrder->id)
            ->where('type', RepairOrderLineType::Package->value)
            ->count())->toBe(1);

    Event::assertDispatchedTimes(RepairOrderEstimateChanged::class, 1);

    $repairOrder->refresh();
    $package = RepairOrderLine::query()
        ->where('repair_order_id', $repairOrder->id)
        ->where('type', RepairOrderLineType::Package->value)
        ->sole();

    $this->delete(route('operations.repair-orders.lines.destroy', [$repairOrder, $package]), [
        RepairOrderConcurrency::FIELD => $repairOrder->estimate_version,
    ])->assertRedirect();

    $repairOrder->refresh();

    expect(RepairOrderLine::query()
        ->where('repair_order_id', $repairOrder->id)
        ->where('type', RepairOrderLineType::Package->value)
        ->count())->toBe(0)
        ->and((int) $repairOrder->estimate_version)->toBe($before + 2);

    Event::assertDispatchedTimes(RepairOrderEstimateChanged::class, 2);

    $service = MaintenanceService::query()->where('repair_order_id', $repairOrder->id)->first();

    app(CancelEngineOilServiceAction::class)->handle($service, $advisor);

    expect((int) $repairOrder->fresh()->estimate_version)->toBe($before + 2);
    Event::assertDispatchedTimes(RepairOrderEstimateChanged::class, 2);
});

test('extra oil quarts create a part line and advance the version once', function () {
    $advisor = actingAsLearnCurrentStaff(ArkRole::Advisor);
    $this->actingAs($advisor);
    $repairOrder = repairOrderForEstimateWorkspace();

    $this->post(route('operations.repair-orders.maintenance.engine-oil.store', $repairOrder), [
        'reset_reminder' => '1',
    ])->assertRedirect();

    $repairOrder->refresh();
    $service = MaintenanceService::query()->where('repair_order_id', $repairOrder->id)->sole();
    $before = (int) $repairOrder->estimate_version;

    Event::fake([RepairOrderEstimateChanged::class]);

    $this->post(route('operations.repair-orders.maintenance.extra-quarts.store', [$repairOrder, $service]), [
        'quarts' => '2',
        'cost_per_quart' => '8.50',
        'description' => 'Extra quarts at cost',
        RepairOrderConcurrency::FIELD => $before,
    ])->assertRedirect();

    $repairOrder->refresh();

    $line = RepairOrderLine::query()
        ->where('repair_order_id', $repairOrder->id)
        ->where('type', RepairOrderLineType::Part->value)
        ->where('description', 'Extra quarts at cost')
        ->sole();

    expect($line->part_cost_cents)->toBe(850)
        ->and((int) $repairOrder->estimate_version)->toBe($before + 1);

    Event::assertDispatchedTimes(RepairOrderEstimateChanged::class, 1);
    Event::assertDispatched(RepairOrderEstimateChanged::class, function (RepairOrderEstimateChanged $event) use ($before): bool {
        return (int) $event->repairOrder->estimate_version === $before + 1;
    });
});

test('testing package creates estimate content and advances the version once', function () {
    $advisor = actingAsLearnCurrentStaff(ArkRole::Advisor);
    $this->actingAs($advisor);
    $repairOrder = repairOrderForEstimateWorkspace();
    $before = (int) $repairOrder->estimate_version;

    Event::fake([RepairOrderEstimateChanged::class]);

    $this->post(route('operations.repair-orders.work-authorization.testing.store', $repairOrder), [
        RepairOrderConcurrency::FIELD => $before,
    ])->assertRedirect();

    $repairOrder->refresh();

    $authorization = WorkAuthorization::query()->where('repair_order_id', $repairOrder->id)->sole();

    expect($authorization->repair_order_line_id)->not->toBeNull()
        ->and(RepairOrderLine::query()->whereKey($authorization->repair_order_line_id)->exists())->toBeTrue()
        ->and((int) $repairOrder->estimate_version)->toBe($before + 1);

    Event::assertDispatchedTimes(RepairOrderEstimateChanged::class, 1);
});

test('recording customer authorization advances the estimate version once', function () {
    $repairOrder = repairOrderForEstimateWorkspace();
    $repairOrder->update(['status' => RepairOrderStatus::WaitingApproval]);
    $concern = concernForEstimateWorkspace($repairOrder);
    $concern->update(['disposition' => RepairOrderConcernDisposition::Approved]);

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Approved diagnostic',
        'quantity' => '1.00',
        'unit_price_cents' => 12000,
    ]);

    $before = (int) $repairOrder->fresh()->estimate_version;

    Event::fake([RepairOrderEstimateChanged::class]);

    $this->post(route('operations.repair-orders.authorization.store', $repairOrder), [
        'source' => ApprovalSource::Phone->value,
        'approved_by' => 'Rosa Garcia',
        RepairOrderConcurrency::FIELD => $before,
    ])->assertRedirect();

    $repairOrder->refresh();
    $approval = ApprovalEvent::query()->where('visit_id', $repairOrder->id)->sole();

    expect($approval->approved_by)->toBe('Rosa Garcia')
        ->and($approval->source)->toBe(ApprovalSource::Phone)
        ->and($approval->isRevoked())->toBeFalse()
        ->and((int) $repairOrder->estimate_version)->toBe($before + 1);

    Event::assertDispatchedTimes(RepairOrderEstimateChanged::class, 1);
    Event::assertDispatched(RepairOrderEstimateChanged::class, function (RepairOrderEstimateChanged $event) use ($before): bool {
        return (int) $event->repairOrder->estimate_version === $before + 1;
    });
});

test('manual deposit emits financial changed after commit without advancing the estimate version', function () {
    $repairOrder = financialCloseoutRepairOrder(RepairOrderStatus::Approved);
    $before = (int) $repairOrder->estimate_version;
    $broadcastLevel = null;
    $financialBroadcasts = 0;
    $estimateBroadcasts = 0;

    Event::listen(RepairOrderFinancialChanged::class, function (RepairOrderFinancialChanged $event) use (&$broadcastLevel, &$financialBroadcasts): void {
        $financialBroadcasts++;
        $broadcastLevel = DB::transactionLevel();

        expect($event->broadcastAs())->toBe('financial.changed')
            ->and($event->broadcastWith()['reason'])->toBe('deposit_recorded');
    });
    Event::listen(RepairOrderEstimateChanged::class, function () use (&$estimateBroadcasts): void {
        $estimateBroadcasts++;
    });

    $this->patch(route('operations.repair-orders.deposit.update', $repairOrder), [
        'amount' => '50.00',
        'payment_method' => PaymentMethod::Cash->value,
        'deposit_confirmed' => '1',
        RepairOrderConcurrency::FIELD => $before,
        FinancialSubmissionIntentGate::FIELD => financialSubmissionKey(),
    ])->assertRedirect();

    $repairOrder->refresh();

    expect((int) $repairOrder->estimate_version)->toBe($before)
        ->and(RepairOrderLedgerEntry::query()
            ->where('repair_order_id', $repairOrder->id)
            ->where('entry_type', LedgerEntryType::Deposit)
            ->where('amount_cents', 5000)
            ->exists())->toBeTrue()
        ->and($broadcastLevel)->toBe(DB::transactionLevel())
        ->and($financialBroadcasts)->toBe(1)
        ->and($estimateBroadcasts)->toBe(0);
});
