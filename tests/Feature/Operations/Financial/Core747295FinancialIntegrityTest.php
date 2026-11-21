<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\RepairOrders\PartProcurementState;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\RepairOrders\ScopeProductionStatus;
use App\Ark\Operations\RepairOrders\UnresolvedAuthorizationReport;
use App\Ark\Operations\RepairOrders\UpdateConcernDispositionAction;
use App\Ark\Operations\RepairOrders\WorkCompletionAuthorization;
use App\Ark\Operations\Reports\OperationalReportTotals;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
    ShopSettings::current()->update([
        'tax_enabled' => false,
        'shop_fee_enabled' => false,
        'default_labor_rate_cents' => 10000,
    ]);
});

test('core 74 pre-invoice sold and labor totals use approved amounts not raw line raises', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    [$repairOrder, $part, $labor, $added] = finIntegrityRaisedPerformedWork($advisor);

    $calculator = app(EstimateTotalsCalculator::class);
    $approved = $calculator->approvedTotalsForRead($repairOrder->fresh(['lines.concern', 'concerns']));
    $authorization = app(WorkCompletionAuthorization::class);

    expect($approved->totalCents())->toBe(28000)
        ->and($approved->laborCents())->toBe(20000)
        ->and($authorization->workOutsideApprovedSalesCents($repairOrder->fresh(['lines.concern'])))->toBe(15000)
        ->and(OperationalReportTotals::soldTotalCentsByRepairOrderId([$repairOrder->id])[$repairOrder->id])->toBe(28000)
        ->and(OperationalReportTotals::laborCentsByRepairOrderId([$repairOrder->id])[$repairOrder->id])->toBe(20000)
        ->and((int) $part->fresh()->total_cents + (int) $labor->fresh()->total_cents + (int) $added->fresh()->total_cents)
        ->toBe(43000);
});

test('core 72 unresolved authorization report includes performed work with unapproved increases', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    [$repairOrder, $part, $labor, $added] = finIntegrityRaisedPerformedWork($advisor);

    $authorization = app(WorkCompletionAuthorization::class);
    $rows = app(UnresolvedAuthorizationReport::class)->rows();
    $byLine = $rows->keyBy('line_id');

    expect($authorization->lineCovered($part->fresh(['concern'])))->toBeTrue()
        ->and($authorization->lineCovered($labor->fresh(['concern'])))->toBeTrue()
        ->and($authorization->lineCovered($added->fresh(['concern'])))->toBeFalse()
        ->and($authorization->approvedSubtotalsBeforeIncrease($repairOrder->fresh(['lines.concern', 'concerns'])))
        ->toHaveKeys([$part->id, $labor->id])
        ->and($byLine->has($part->id))->toBeTrue()
        ->and($byLine[$part->id]['kind'])->toBe('installed_part')
        ->and($byLine[$part->id]['pending_approval_cents'])->toBe(4000)
        ->and($byLine->has($labor->id))->toBeTrue()
        ->and($byLine[$labor->id]['kind'])->toBe('completed_labor')
        ->and($byLine[$labor->id]['pending_approval_cents'])->toBe(6000)
        ->and($byLine->has($added->id))->toBeTrue()
        ->and($byLine[$added->id]['kind'])->toBe('installed_part')
        ->and($byLine[$added->id]['pending_approval_cents'])->toBe(5000);
});

test('core 95 raised approved line shows approved ceiling and pending increase next to the line', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $this->actingAs($advisor);

    [$repairOrder, $part] = finIntegrityApprovedPartThenRaised($advisor, approvedCents: 10000, raisedCents: 24750);

    $html = $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Increase needs authorization', false)
        ->assertSee('data-line-pending-increase="'.$part->id.'"', false)
        ->assertSee('data-line-approved-ceiling="'.$part->id.'"', false)
        ->assertSee('Approved at $100.00', false)
        ->assertSee('+$147.50 pending authorization', false)
        ->assertSee('data-approved-line-increase="'.$part->id.'"', false)
        ->getContent();

    expect($html)
        ->toContain('$247.50')
        ->and(app(EstimateTotalsCalculator::class)->approvedTotalsForRead($repairOrder->fresh(['lines.concern']))->totalCents())
        ->toBe(10000);
});

/**
 * @return array{0: RepairOrder, 1: RepairOrderLine, 2: RepairOrderLine, 3: RepairOrderLine}
 */
function finIntegrityRaisedPerformedWork(User $advisor): array
{
    $customer = Customer::query()->create([
        'first_name' => 'Fin',
        'last_name' => 'Integrity',
        'phone' => '555-'.random_int(1000, 9999),
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'plate' => 'FIN'.random_int(100, 999),
        'year' => 2014,
        'make' => 'Ford',
        'model' => 'F-150',
        'vin' => '1FTMF1CT5EKE'.random_int(10000, 99999),
    ]);
    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'mileage_in' => 1,
        'mileage_out' => 2,
        'concern_summary' => 'Brakes',
    ]);

    $partConcern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Brakes (part)',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'position' => 1,
    ]);
    $part = RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $partConcern->id,
        'type' => RepairOrderLineType::Part,
        'description' => 'Brake pads',
        'quantity' => '1.00',
        'unit_price_cents' => 8000,
        'part_cost_cents' => 4000,
        'subtotal_cents' => 8000,
        'total_cents' => 8000,
    ]);
    app(UpdateConcernDispositionAction::class)->execute(
        $repairOrder,
        $partConcern,
        RepairOrderConcernDisposition::Approved,
        $advisor,
    );
    $part->update(['procurement_state' => PartProcurementState::Installed]);
    $part->update(['unit_price_cents' => 12000, 'subtotal_cents' => 12000, 'total_cents' => 12000]);

    $laborConcern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Brakes (labor)',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'position' => 2,
    ]);
    $labor = RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $laborConcern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Replace pads',
        'quantity' => '2.00',
        'unit_price_cents' => 10000,
        'subtotal_cents' => 20000,
        'total_cents' => 20000,
    ]);
    app(UpdateConcernDispositionAction::class)->execute(
        $repairOrder->fresh(),
        $laborConcern,
        RepairOrderConcernDisposition::Approved,
        $advisor,
    );
    $laborConcern->update(['production_status' => ScopeProductionStatus::Completed->value]);
    $labor->update(['unit_price_cents' => 13000, 'subtotal_cents' => 26000, 'total_cents' => 26000]);

    $added = RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $partConcern->id,
        'type' => RepairOrderLineType::Part,
        'description' => 'Hardware kit',
        'quantity' => '1.00',
        'unit_price_cents' => 5000,
        'part_cost_cents' => 2000,
        'subtotal_cents' => 5000,
        'total_cents' => 5000,
        'procurement_state' => PartProcurementState::Installed,
    ]);

    app(EstimateTotalsCalculator::class)->recalculateRepairOrder($repairOrder->fresh());

    return [$repairOrder->fresh(['lines.concern', 'concerns']), $part->fresh(), $labor->fresh(), $added->fresh()];
}

/**
 * @return array{0: RepairOrder, 1: RepairOrderLine}
 */
function finIntegrityApprovedPartThenRaised(User $advisor, int $approvedCents, int $raisedCents): array
{
    $customer = Customer::query()->create([
        'first_name' => 'Ui',
        'last_name' => 'Raise',
        'phone' => '555-'.random_int(1000, 9999),
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'plate' => 'UI'.random_int(100, 999),
        'year' => 2018,
        'make' => 'Toyota',
        'model' => 'Camry',
        'vin' => '4T1B11HK5JU'.random_int(100000, 999999),
    ]);
    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'mileage_in' => 10,
        'mileage_out' => 11,
        'concern_summary' => 'Caliper',
    ]);
    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Front caliper',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'position' => 1,
    ]);
    $part = RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Part,
        'description' => 'Caliper',
        'quantity' => '1.00',
        'unit_price_cents' => $approvedCents,
        'part_cost_cents' => intdiv($approvedCents, 2),
        'subtotal_cents' => $approvedCents,
        'total_cents' => $approvedCents,
    ]);

    app(UpdateConcernDispositionAction::class)->execute(
        $repairOrder,
        $concern,
        RepairOrderConcernDisposition::Approved,
        $advisor,
    );

    $part->update([
        'unit_price_cents' => $raisedCents,
        'subtotal_cents' => $raisedCents,
        'total_cents' => $raisedCents,
    ]);
    app(EstimateTotalsCalculator::class)->recalculateRepairOrder($repairOrder->fresh());

    return [$repairOrder->fresh(['lines.concern', 'concerns']), $part->fresh()];
}
