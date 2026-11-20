<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\RepairOrders\ConcernBillingPosture;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkCapability;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    ShopSettings::current()->update([
        'labor_categories' => ShopSettings::DEFAULT_LABOR_CATEGORIES,
        'default_labor_rate_cents' => 16500,
    ]);
    $this->advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $this->actingAs($this->advisor);
});

function exactLaborRepairOrder(): RepairOrder
{
    $customer = Customer::query()->create(['first_name' => 'Exact', 'last_name' => 'Charge', 'phone' => '555-0223']);
    $vehicle = Vehicle::query()->create(['customer_id' => $customer->id, 'year' => 2020, 'make' => 'Toyota', 'model' => 'Camry']);

    return RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Exact manual labor',
    ]);
}

function exactLaborConcern(RepairOrder $repairOrder): RepairOrderConcern
{
    return RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Brake job',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'position' => 1,
        'billing_posture' => ConcernBillingPosture::CustomerPay,
    ]);
}

/**
 * @param  array<string, mixed>  $overrides
 */
function exactLaborStore(RepairOrder $repairOrder, RepairOrderConcern $concern, array $overrides = []): RepairOrderLine
{
    $description = 'Labor '.uniqid();

    test()->post(route('operations.repair-orders.lines.store', $repairOrder), [
        'repair_order_concern_id' => $concern->id,
        'type' => 'labor',
        'description' => $description,
        'labor_category_key' => 'mechanical',
        'labor_entered_hours' => '1.00',
        'labor_adjustment' => 'normal',
        'labor_rate_overridden' => '0',
        ...$overrides,
    ])->assertSessionHasNoErrors();

    return RepairOrderLine::query()->where('description', $description)->sole();
}

/**
 * @param  array<string, mixed>  $overrides
 */
function exactLaborPatch(RepairOrder $repairOrder, RepairOrderLine $line, array $overrides = []): void
{
    $line = $line->fresh();
    $rateCents = $line->labor_rate_cents ?? $line->unit_price_cents;

    test()->patch(route('operations.repair-orders.lines.update', [$repairOrder, $line]), [
        'repair_order_concern_id' => $line->repair_order_concern_id,
        'type' => 'labor',
        'description' => $line->description,
        'labor_category_key' => $line->labor_category_key,
        'labor_entered_hours' => (string) $line->labor_entered_hours,
        'labor_adjustment' => 'normal',
        'unit_price' => number_format(((int) $rateCents) / 100, 2, '.', ''),
        'labor_rate_overridden' => '0',
        'labor_charge_overridden' => $line->pricing_mode === 'manual' ? '1' : '0',
        'labor_charge' => $line->pricing_mode === 'manual'
            ? number_format(((int) $line->unit_price_cents) / 100, 2, '.', '')
            : null,
        'pricing_mode' => $line->pricing_mode === 'manual' ? 'manual' : null,
        ...$overrides,
    ])->assertSessionHasNoErrors();
}

test('exact labor charge of $90 saves, reopens, and survives hour edits', function () {
    $repairOrder = exactLaborRepairOrder();
    $concern = exactLaborConcern($repairOrder);

    $line = exactLaborStore($repairOrder, $concern, [
        'labor_entered_hours' => '2.00',
        'labor_charge' => '90.00',
        'labor_charge_overridden' => '1',
        'pricing_mode' => 'manual',
        'unit_price' => '165.00',
    ]);

    expect($line->pricing_mode)->toBe('manual')
        ->and($line->unit_price_cents)->toBe(9000)
        ->and($line->labor_rate_cents)->toBe(16500)
        ->and($line->quantity)->toBe('2.00')
        ->and($line->subtotal_cents)->toBe(9000)
        ->and($line->is_overridden)->toBeTrue();

    $this->get(route('operations.repair-orders.show', ['repairOrder' => $repairOrder, 'editing_line' => $line->id]))
        ->assertOk()
        ->assertSee("laborCharge: '90.00'", false)
        ->assertSee('laborChargeEdited: true', false)
        ->assertSee("sell: '165.00'", false)
        ->assertSee('data-labor-charge', false)
        ->assertSee('data-use-calculated-labor-charge', false);

    exactLaborPatch($repairOrder, $line, [
        'labor_entered_hours' => '4.00',
        'labor_charge' => '90.00',
        'labor_charge_overridden' => '1',
        'pricing_mode' => 'manual',
        'unit_price' => '165.00',
    ]);

    $line = $line->fresh();
    expect($line->quantity)->toBe('4.00')
        ->and($line->unit_price_cents)->toBe(9000)
        ->and($line->subtotal_cents)->toBe(9000)
        ->and($line->pricing_mode)->toBe('manual');

    app(EstimateTotalsCalculator::class)->recalculateRepairOrder($repairOrder->fresh());

    expect($line->fresh()->subtotal_cents)->toBe(9000)
        ->and($repairOrder->fresh()->lines->sum('subtotal_cents'))->toBe(9000);
});

test('use calculated labor charge clears the manual override', function () {
    $repairOrder = exactLaborRepairOrder();
    $line = exactLaborStore($repairOrder, exactLaborConcern($repairOrder), [
        'labor_entered_hours' => '2.00',
        'labor_charge' => '90.00',
        'labor_charge_overridden' => '1',
        'pricing_mode' => 'manual',
        'unit_price' => '165.00',
    ]);

    exactLaborPatch($repairOrder, $line, [
        'labor_entered_hours' => '2.00',
        'unit_price' => '165.00',
        'labor_charge_overridden' => '0',
        'use_calculated_labor_charge' => '1',
        'pricing_mode' => null,
        'labor_charge' => null,
    ]);

    $line = $line->fresh();
    expect($line->pricing_mode)->toBeNull()
        ->and($line->unit_price_cents)->toBe(16500)
        ->and($line->labor_rate_cents)->toBe(16500)
        ->and($line->quantity)->toBe('2.00')
        ->and($line->subtotal_cents)->toBe(33000)
        ->and($line->is_overridden)->toBeFalse();
});

test('manual labor charge requires pricing authority', function () {
    $repairOrder = exactLaborRepairOrder();
    $concern = exactLaborConcern($repairOrder);

    $withoutPricing = User::factory()->create();
    $withoutPricing->givePermissionTo(
        Role::findByName(ArkRole::Advisor->value, 'web')->permissions
            ->reject(fn ($permission): bool => $permission->name === ArkCapability::PricingOverride->value),
    );
    $this->actingAs($withoutPricing);

    $this->post(route('operations.repair-orders.lines.store', $repairOrder), [
        'repair_order_concern_id' => $concern->id,
        'type' => 'labor',
        'description' => 'Blocked charge',
        'labor_category_key' => 'mechanical',
        'labor_entered_hours' => '1.00',
        'labor_adjustment' => 'normal',
        'labor_charge' => '90.00',
        'labor_charge_overridden' => '1',
        'pricing_mode' => 'manual',
        'unit_price' => '165.00',
    ])->assertSessionHasErrors('labor_charge');

    expect(RepairOrderLine::query()->where('description', 'Blocked charge')->exists())->toBeFalse();
});

test('calculated labor lines still bill hours times rate', function () {
    $repairOrder = exactLaborRepairOrder();
    $line = exactLaborStore($repairOrder, exactLaborConcern($repairOrder), [
        'labor_entered_hours' => '2.00',
        'unit_price' => '165.00',
    ]);

    expect($line->pricing_mode)->toBeNull()
        ->and($line->unit_price_cents)->toBe(16500)
        ->and($line->subtotal_cents)->toBe(33000)
        ->and($line->is_overridden)->toBeFalse();
});

test('raising a manual labor charge on approved work captures a legacy approval ceiling', function () {
    $repairOrder = exactLaborRepairOrder();
    $concern = exactLaborConcern($repairOrder);
    $line = exactLaborStore($repairOrder, $concern, [
        'labor_entered_hours' => '1.00',
        'labor_charge' => '90.00',
        'labor_charge_overridden' => '1',
        'pricing_mode' => 'manual',
        'unit_price' => '165.00',
    ]);

    $concern->update(['disposition' => RepairOrderConcernDisposition::Approved]);

    exactLaborPatch($repairOrder, $line, [
        'labor_charge' => '120.00',
        'labor_charge_overridden' => '1',
        'pricing_mode' => 'manual',
        'unit_price' => '165.00',
    ]);

    $line = $line->fresh();
    expect($line->unit_price_cents)->toBe(12000)
        ->and($line->subtotal_cents)->toBe(12000)
        ->and($line->legacy_approved_amounts)->not->toBeNull()
        ->and($line->legacy_approved_amounts['unit_price_cents'])->toBe(9000)
        ->and($line->legacy_approved_amounts['subtotal_cents'])->toBe(9000);
});
