<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\EstimatePricing\ShopFeeProfile;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\RepairOrders\ConcernBillingPosture;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    ShopSettings::current()->update([
        'labor_categories' => ShopSettings::DEFAULT_LABOR_CATEGORIES,
        'default_labor_rate_cents' => 16500,
        'tax_enabled' => false,
        'shop_fee_enabled' => true,
        'shop_fee_mode' => 'flat',
        'shop_fee_rate' => '5.000',
        'shop_fee_flat_cents' => 2500,
        'shop_fee_cap_cents' => null,
        'taxable_shop_fees' => false,
    ]);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value));
});

function flatFeeRepairOrder(string $customerType = 'Retail'): RepairOrder
{
    $customer = Customer::query()->create([
        'first_name' => 'Synthetic',
        'last_name' => 'FlatFee',
        'phone' => '555-0199',
        'customer_type' => $customerType,
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2020,
        'make' => 'Honda',
        'model' => 'Civic',
    ]);

    return RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Synthetic flat shop fee',
    ]);
}

function flatFeeLabor(RepairOrder $repairOrder, string $summary, ConcernBillingPosture $posture = ConcernBillingPosture::CustomerPay): RepairOrderLine
{
    test()->post(route('operations.repair-orders.concerns.store', $repairOrder), [
        'summary' => $summary,
    ])->assertSessionHasNoErrors();

    $concern = RepairOrderConcern::query()
        ->where('repair_order_id', $repairOrder->id)
        ->where('summary', 'like', $summary.'%')
        ->sole();
    $concern->update([
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'billing_posture' => $posture,
    ]);

    $description = 'Labor '.uniqid();
    test()->post(route('operations.repair-orders.lines.store', $repairOrder), [
        'repair_order_concern_id' => $concern->id,
        'type' => 'labor',
        'description' => $description,
        'labor_category_key' => 'mechanical',
        'labor_entered_hours' => '1.00',
        'labor_adjustment' => 'normal',
        'labor_rate_overridden' => '0',
    ])->assertSessionHasNoErrors();

    return RepairOrderLine::query()->where('description', $description)->sole();
}

test('flat shop fee is charged once per repair order across eligible lines', function () {
    $repairOrder = flatFeeRepairOrder();
    $first = flatFeeLabor($repairOrder, 'First concern');
    $second = flatFeeLabor($repairOrder, 'Second concern');

    expect($repairOrder->fresh()->shop_fee_profile['mode'])->toBe('flat')
        ->and($repairOrder->fresh()->shop_fee_profile['flat_cents'])->toBe(2500)
        ->and((int) $repairOrder->lines()->sum('shop_fee_cents'))->toBe(2500)
        ->and($first->fresh()->shop_fee_cents + $second->fresh()->shop_fee_cents)->toBe(2500);
});

test('adding another concern does not multiply the flat shop fee', function () {
    $repairOrder = flatFeeRepairOrder();
    flatFeeLabor($repairOrder, 'One');
    flatFeeLabor($repairOrder, 'Two');
    flatFeeLabor($repairOrder, 'Three');

    expect((int) $repairOrder->lines()->sum('shop_fee_cents'))->toBe(2500);
});

test('warranty-only work does not receive a flat shop fee', function () {
    $repairOrder = flatFeeRepairOrder();
    $line = flatFeeLabor($repairOrder, 'Warranty only', ConcernBillingPosture::WarrantyOther);

    expect($line->fresh()->shop_fee_cents)->toBe(0)
        ->and((int) $repairOrder->lines()->sum('shop_fee_cents'))->toBe(0);
});

test('changing flat fee settings does not reprice an existing stamped repair order', function () {
    $repairOrder = flatFeeRepairOrder();
    flatFeeLabor($repairOrder, 'Locked fee');
    expect((int) $repairOrder->lines()->sum('shop_fee_cents'))->toBe(2500);

    $this->patch(route('operations.settings.shop.fees.update'), [
        'shop_fee_enabled' => '1',
        'shop_fee_mode' => 'flat',
        'shop_fee_flat' => '50.00',
        'shop_fee_rate' => '5.000',
    ])->assertRedirect();

    expect(ShopSettings::current()->shop_fee_flat_cents)->toBe(5000);

    app(EstimateTotalsCalculator::class)->ensureFinancialTotalsAreCurrent($repairOrder->fresh());

    expect((int) $repairOrder->lines()->sum('shop_fee_cents'))->toBe(2500)
        ->and($repairOrder->fresh()->shop_fee_profile['flat_cents'])->toBe(2500);

    $newer = flatFeeRepairOrder();
    flatFeeLabor($newer, 'Uses new flat');
    expect((int) $newer->lines()->sum('shop_fee_cents'))->toBe(5000)
        ->and($newer->fresh()->shop_fee_profile['flat_cents'])->toBe(5000);
});

test('saving fee settings stamps legacy living repair orders before the change', function () {
    ShopSettings::current()->update([
        'shop_fee_mode' => 'percent',
        'shop_fee_rate' => '5.000',
        'shop_fee_flat_cents' => null,
    ]);

    $legacy = flatFeeRepairOrder();
    $legacy->forceFill(['shop_fee_profile' => null])->saveQuietly();
    flatFeeLabor($legacy, 'Legacy percent');
    expect((int) $legacy->lines()->sum('shop_fee_cents'))->toBe(825); // 5% of 16500

    $this->patch(route('operations.settings.shop.fees.update'), [
        'shop_fee_enabled' => '1',
        'shop_fee_mode' => 'flat',
        'shop_fee_flat' => '40.00',
        'shop_fee_rate' => '5.000',
    ])->assertRedirect();

    $legacy->refresh();
    expect($legacy->shop_fee_profile)->not->toBeNull()
        ->and($legacy->shop_fee_profile['mode'])->toBe('percent')
        ->and($legacy->shop_fee_profile['rate'])->toBe('5.000');

    app(EstimateTotalsCalculator::class)->ensureFinancialTotalsAreCurrent($legacy);
    expect((int) $legacy->lines()->sum('shop_fee_cents'))->toBe(825);
});

test('shop fee settings page exposes percent and flat choices', function () {
    $this->get(route('operations.settings.shop.edit', ['section' => 'financial', 'tab' => 'shop-fees']))
        ->assertOk()
        ->assertSee('Percentage of eligible charges')
        ->assertSee('Fixed dollar fee per RO')
        ->assertSee('Flat fee per RO ($)')
        ->assertSee('Applies to new repair orders only');
});

test('shop fee profile serializes from shop settings', function () {
    $profile = ShopFeeProfile::fromShopSettings(ShopSettings::current());

    expect($profile->isFlat())->toBeTrue()
        ->and($profile->flatCents)->toBe(2500)
        ->and($profile->toArray()['schema'])->toBe(1);
});
