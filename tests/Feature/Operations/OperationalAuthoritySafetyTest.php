<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\RepairOrders\PartProcurementState;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderPaymentStatus;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkCapability;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Spatie\Permission\Models\Permission;

test('pricing overrides require explicit pricing authority', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    ShopSettings::current()->update(['parts_matrices' => ShopSettings::DEFAULT_PARTS_MATRICES]);

    $user = authorityUserWithout(ArkCapability::PricingOverride);
    $this->actingAs($user);

    [$repairOrder, $concern] = authorityRepairOrderFixture();

    $this->post(route('operations.repair-orders.lines.store', $repairOrder), [
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Part->value,
        'description' => 'Brake pads',
        'quantity' => '1.00',
        'part_cost' => '50.00',
        'pricing_mode' => 'matrix',
        'pricing_matrix_key' => 'aft-parts',
        'pricing_matrix_explicit' => '1',
        'unit_price' => '99.00',
    ])->assertForbidden();

    expect(RepairOrderLine::query()->count())->toBe(0);
});

test('estimate document generation requires document authority', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $user = authorityUserWithout(ArkCapability::EstimateDocumentsManage);
    $this->actingAs($user);

    [$repairOrder] = authorityRepairOrderFixture();

    $this->post(route('operations.repair-orders.estimate-documents.store', $repairOrder))
        ->assertForbidden();
});

test('payment closeout requires closeout authority', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $user = authorityUserWithout(ArkCapability::RepairOrdersCloseout);
    $this->actingAs($user);

    [$repairOrder, $concern] = authorityRepairOrderFixture(status: RepairOrderStatus::ReadyPickup);
    authorityLine($repairOrder, $concern);

    $this->patch(route('operations.repair-orders.payment.update', $repairOrder))
        ->assertForbidden();

    expect($repairOrder->fresh()->paymentStatus())->toBe(RepairOrderPaymentStatus::Unpaid);
});

test('destructive estimate cleanup requires destructive authority', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $user = authorityUserWithout(ArkCapability::RepairOrdersDestructive);
    $this->actingAs($user);

    [$repairOrder, $concern] = authorityRepairOrderFixture();
    $line = authorityLine($repairOrder, $concern);

    $this->delete(route('operations.repair-orders.lines.destroy', [$repairOrder, $line]))
        ->assertForbidden();

    expect($line->fresh())->not->toBeNull();
});

test('part cancellation requires procurement cancellation authority', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $user = authorityUserWithout(ArkCapability::ProcurementCancel);
    $this->actingAs($user);

    [$repairOrder, $concern] = authorityRepairOrderFixture();
    $line = authorityLine($repairOrder, $concern, RepairOrderLineType::Part);

    $this->from(route('operations.repair-orders.show', $repairOrder))
        ->patch(route('operations.repair-orders.lines.procurement.update', [$repairOrder, $line]), [
            'procurement_state' => PartProcurementState::Canceled->value,
        ])
        ->assertRedirect(route('operations.repair-orders.show', $repairOrder))
        ->assertSessionHas('error', 'Canceling a part order requires procurement authority.');

    expect($line->fresh()->procurementState())->toBe(PartProcurementState::None);
});

test('technician keeps execution visibility without mutation authority clutter', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $this->actingAs($technician);

    [$repairOrder, $concern] = authorityRepairOrderFixture();
    authorityLine($repairOrder, $concern);

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertDontSee('Operational Timeline')
        ->assertDontSee('Vehicle History Signal')
        ->assertDontSee('Edit Estimate')
        ->assertDontSee('>Editing<', false)
        ->assertDontSee('Record Payment')
        ->assertDontSee('Generate Final Invoice');

    expect($technician->can(ArkCapability::RepairOrdersView->value))->toBeTrue()
        ->and($technician->can(ArkCapability::RepairOrdersManage->value))->toBeFalse()
        ->and($technician->can(ArkCapability::PricingOverride->value))->toBeFalse();
});

function authorityUserWithout(ArkCapability $capability): User
{
    $user = User::factory()->create();
    $permissions = collect(config('ark-permissions.roles.'.ArkRole::Advisor->value))
        ->reject(fn (string $permission): bool => $permission === $capability->value)
        ->map(fn (string $permission): Permission => Permission::findByName($permission))
        ->all();

    $user->givePermissionTo($permissions);

    return $user;
}

/**
 * @return array{0: RepairOrder, 1: RepairOrderConcern}
 */
function authorityRepairOrderFixture(RepairOrderStatus $status = RepairOrderStatus::Estimate): array
{
    $customer = Customer::query()->create([
        'first_name' => 'Authority',
        'last_name' => 'Customer',
        'phone' => '555-0140',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2021,
        'make' => 'Ford',
        'model' => 'F-150',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => $status,
        'concern_summary' => 'Customer requests operational authority test.',
    ]);

    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Brake inspection',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'recommendation_intent' => 'maintenance',
        'position' => 1,
    ]);

    return [$repairOrder, $concern];
}

function authorityLine(RepairOrder $repairOrder, RepairOrderConcern $concern, RepairOrderLineType $type = RepairOrderLineType::Labor): RepairOrderLine
{
    $line = RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => $type,
        'description' => $type === RepairOrderLineType::Part ? 'Brake pads' : 'Inspect brakes',
        'quantity' => '1.00',
        'unit_price_cents' => 15000,
        'part_cost_cents' => $type === RepairOrderLineType::Part ? 6000 : null,
        'procurement_state' => PartProcurementState::None,
        'subtotal_cents' => 15000,
        'tax_cents' => 0,
        'shop_fee_cents' => 0,
        'total_cents' => 15000,
    ]);

    app(EstimateTotalsCalculator::class)->recalculateRepairOrder($repairOrder);

    return $line->fresh();
}
