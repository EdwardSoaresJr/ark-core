<?php

use App\Ark\Operations\Approvals\ApprovalSource;
use App\Ark\Operations\Approvals\ApprovalType;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Documents\EstimateDocument;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\RepairOrders\PartProcurementState;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderConcurrency;
use App\Ark\Operations\RepairOrders\RepairOrderEstimateVersion;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

test('stale estimate line update is rejected after another advisor changes the repair order', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $otherAdvisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $this->actingAs($advisor);

    [$repairOrder, $concern, $line] = concurrencyRepairOrderFixture();
    $openedVersion = app(RepairOrderConcurrency::class)->openedVersion($repairOrder);

    RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Advisor-added brake vibration',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'recommendation_intent' => 'maintenance',
        'position' => 2,
    ]);

    app(RepairOrderEstimateVersion::class)->bump($repairOrder->fresh(), $otherAdvisor);

    $this->patchJson(route('operations.repair-orders.lines.update', [$repairOrder, $line]), [
        RepairOrderConcurrency::FIELD => $openedVersion,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor->value,
        'description' => 'Overwrite from stale worksheet',
        'quantity' => '2.00',
        'unit_price' => '150.00',
    ])->assertStatus(409)
        ->assertJsonPath('conflict', true);

    expect($line->fresh()->description)->toBe('Initial diagnostic labor');
});

test('fresh estimate mutation still succeeds after reviewing latest state', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $this->actingAs($advisor);

    [$repairOrder, $concern, $line] = concurrencyRepairOrderFixture();
    $openedVersion = app(RepairOrderConcurrency::class)->openedVersion($repairOrder);

    $this->patch(route('operations.repair-orders.lines.update', [$repairOrder, $line]), [
        RepairOrderConcurrency::FIELD => $openedVersion,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor->value,
        'description' => 'Reviewed diagnostic labor',
        'quantity' => '1.50',
        'unit_price' => '150.00',
    ])->assertRedirect(route('operations.repair-orders.show', $repairOrder).'#estimate-lines');

    expect($line->fresh()->description)->toBe('Reviewed diagnostic labor')
        ->and($line->fresh()->total_cents)->toBeGreaterThan(0)
        ->and($repairOrder->fresh()->estimate_version)->toBe(2);
});

test('approval arrival blocks stale estimate document snapshot generation', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $this->actingAs($advisor);

    [$repairOrder, $concern] = concurrencyRepairOrderFixture();
    $openedVersion = app(RepairOrderConcurrency::class)->openedVersion($repairOrder);

    $repairOrder->approvalEvents()->create([
        'estimate_snapshot_reference' => 'approval-arrived',
        'approval_type' => ApprovalType::Repair,
        'approved_amount_cents' => 22500,
        'source' => ApprovalSource::Phone,
        'approved_by' => 'Customer',
        'approved_at' => now(),
        'notes' => 'Customer approved while advisor had an old review open.',
    ]);

    $this->post(route('operations.repair-orders.estimate-documents.store', $repairOrder), [
        RepairOrderConcurrency::FIELD => $openedVersion,
    ])->assertStatus(409);

    expect(EstimateDocument::query()->count())->toBe(0)
        ->and($concern->fresh()->disposition)->toBe(RepairOrderConcernDisposition::Recommended);
});

test('parts status update blocks stale workflow movement from another session', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $otherAdvisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $this->actingAs($advisor);

    [$repairOrder, $concern, $line] = concurrencyRepairOrderFixture(lineType: RepairOrderLineType::Part);
    $concern->update(['disposition' => RepairOrderConcernDisposition::Approved]);
    $repairOrder->update(['status' => RepairOrderStatus::Approved]);
    $openedVersion = app(RepairOrderConcurrency::class)->openedVersion($repairOrder->fresh());

    $line->update(['procurement_state' => PartProcurementState::Backordered]);
    app(RepairOrderEstimateVersion::class)->bump($repairOrder->fresh(), $otherAdvisor);

    $this->patch(route('operations.repair-orders.lifecycle.update', $repairOrder), [
        RepairOrderConcurrency::FIELD => $openedVersion,
        'status' => RepairOrderStatus::ReadyForWork->value,
    ])->assertStatus(409);

    expect($repairOrder->fresh()->status->is(RepairOrderStatus::Approved))->toBeTrue()
        ->and($line->fresh()->procurementState())->toBe(PartProcurementState::Backordered);
});

test('review renders estimate version token and collaboration hooks', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    [$repairOrder] = concurrencyRepairOrderFixture();

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee(RepairOrderConcurrency::FIELD)
        ->assertSee('data-worksheet-root')
        ->assertSee('arkWorksheetContinuity')
        ->assertSee('estimate-details')
        ->assertSee('estimate-review-rail')
        ->assertSee('worksheet-sessions');
});

test('builder renders estimate version token and collaboration hooks', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    [$repairOrder] = concurrencyRepairOrderFixture();

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee(RepairOrderConcurrency::FIELD)
        ->assertSee('data-worksheet-root')
        ->assertSee('arkWorksheetContinuity')
        ->assertSee('initWorksheetCollaboration')
        ->assertSee('estimate-lines')
        ->assertSee('worksheet-sessions');
});

test('review and builder workspace tabs live in the main column with builder first', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    [$repairOrder] = concurrencyRepairOrderFixture();

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('repair-order-workspace-tabs')
        ->assertSee('arkRoWorkspaceTabs')
        ->assertSee('Estimate')
        ->assertSee('Comms')
        ->assertSee('Inspect')
        ->assertSee('communication-rail')
        ->assertDontSee('repair-order-rail-tabs');

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Comms')
        ->assertSee('communication-rail', false)
        ->assertSee('Portal')
        ->assertSee('portal-rail', false)
        ->assertDontSee('ops-review-toolbar-section--trailing', false)
        ->assertSee('data-workspace-tab-panel="comms"', false)
        ->assertSee('workspace-tabs', false)
        ->assertDontSee('id="communication-rail"', false)
        ->assertDontSee("selectTab('send')", false)
        ->assertSee('Auth')
        ->assertSee('History')
        ->assertSee('data-workspace-tab-panel="history"', false)
        ->assertDontSee('Vehicle History', false)
        ->assertDontSee('Relationship Context')
        ->assertDontSee('Customer relationship projection');

    $this->get(route('operations.repair-orders.workspace-tabs.show', ['repairOrder' => $repairOrder, 'tab' => 'portal']))
        ->assertOk()
        ->assertSee('Preview customer estimate');

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('repair-order-workspace-tabs')
        ->assertSee('ops-ro-workspace-tabs--builder-only', false)
        ->assertSee('Building Estimate')
        ->assertDontSee("selectTab('comms')", false)
        ->assertDontSee('Authorization History');
});

/**
 * @return array{0: RepairOrder, 1: RepairOrderConcern, 2: RepairOrderLine}
 */
function concurrencyRepairOrderFixture(RepairOrderLineType $lineType = RepairOrderLineType::Labor): array
{
    $customer = Customer::query()->create([
        'first_name' => 'Shared',
        'last_name' => 'Shop',
        'phone' => '555-0123',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2020,
        'make' => 'Toyota',
        'model' => 'Tacoma',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Customer reports vibration.',
    ]);

    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Vibration diagnosis',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'recommendation_intent' => 'maintenance',
        'position' => 1,
    ]);

    $line = RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => $lineType,
        'description' => $lineType === RepairOrderLineType::Part ? 'Front brake rotor' : 'Initial diagnostic labor',
        'quantity' => '1.00',
        'unit_price_cents' => 15000,
        'part_cost_cents' => $lineType === RepairOrderLineType::Part ? 6500 : null,
        'procurement_state' => $lineType === RepairOrderLineType::Part ? PartProcurementState::None : PartProcurementState::None,
        'subtotal_cents' => 15000,
        'tax_cents' => 0,
        'shop_fee_cents' => 0,
        'total_cents' => 15000,
    ]);

    app(EstimateTotalsCalculator::class)->recalculateRepairOrder($repairOrder);

    return [$repairOrder->fresh(), $concern->fresh(), $line->fresh()];
}
