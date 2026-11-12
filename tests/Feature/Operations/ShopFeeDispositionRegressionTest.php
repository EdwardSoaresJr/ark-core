<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\RepairOrders\ConcernBillingPosture;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

/**
 * Locks percentage and flat-per-RO fee behavior against Core main expectations.
 *
 * Intentional branch differences vs main:
 * - flat-per-RO no longer applies shop_fee_cap_cents (owner: caps are percent/per-concern only)
 * - disposition filtering was NOT applied to percent/flat (would have changed established fees)
 */
beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    ShopSettings::current()->update([
        'labor_categories' => ShopSettings::DEFAULT_LABOR_CATEGORIES,
        'default_labor_rate_cents' => 16500,
        'tax_enabled' => false,
        'taxable_shop_fees' => false,
        'shop_fee_enabled' => true,
        'shop_fee_mode' => 'percent',
        'shop_fee_rate' => '10.000',
        'shop_fee_flat_cents' => 2500,
        'shop_fee_cap_cents' => 1000,
    ]);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value));
});

function dispositionFeeRo(): RepairOrder
{
    $customer = Customer::query()->create([
        'first_name' => 'Synthetic',
        'last_name' => 'FeeDisposition',
        'phone' => '555-0177',
        'customer_type' => 'Retail',
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2018,
        'make' => 'Honda',
        'model' => 'Civic',
    ]);

    return RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Fee disposition regression',
    ]);
}

function dispositionFeeLabor(
    RepairOrder $repairOrder,
    string $summary,
    RepairOrderConcernDisposition $disposition,
    int $unitPriceCents = 10000,
): RepairOrderLine {
    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => $summary,
        'disposition' => $disposition,
        'billing_posture' => ConcernBillingPosture::CustomerPay,
    ]);

    $line = RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => $summary.' labor',
        'quantity' => '1.00',
        'unit_price_cents' => $unitPriceCents,
        'subtotal_cents' => $unitPriceCents,
        'tax_cents' => 0,
        'shop_fee_cents' => 0,
        'total_cents' => $unitPriceCents,
        'labor_category_key' => 'mechanical',
        'labor_entered_hours' => '1.00',
    ]);

    app(EstimateTotalsCalculator::class)->ensureFinancialTotalsAreCurrent($repairOrder->fresh(['lines', 'concerns', 'customer']));

    return $line->fresh();
}

test('percentage recommended-only matches main: each eligible line keeps its own percent fee', function () {
    ShopSettings::current()->update(['shop_fee_mode' => 'percent', 'shop_fee_rate' => '10.000', 'shop_fee_cap_cents' => null]);

    $ro = dispositionFeeRo();
    $a = dispositionFeeLabor($ro, 'Rec A', RepairOrderConcernDisposition::Recommended, 10000);
    $b = dispositionFeeLabor($ro, 'Rec B', RepairOrderConcernDisposition::Recommended, 10000);

    expect($a->fresh()->shop_fee_cents)->toBe(1000)
        ->and($b->fresh()->shop_fee_cents)->toBe(1000)
        ->and((int) $ro->lines()->sum('shop_fee_cents'))->toBe(2000)
        ->and(app(EstimateTotalsCalculator::class)->totalsFor($ro->fresh())->allocatedShopFeesCents())->toBe(2000);
});

test('percentage with declined lines still stores fees on declined lines as main did', function () {
    ShopSettings::current()->update(['shop_fee_mode' => 'percent', 'shop_fee_rate' => '10.000', 'shop_fee_cap_cents' => null]);

    $ro = dispositionFeeRo();
    $recommended = dispositionFeeLabor($ro, 'Keep', RepairOrderConcernDisposition::Recommended, 10000);
    $declined = dispositionFeeLabor($ro, 'Decline', RepairOrderConcernDisposition::Declined, 10000);

    // Main wrote percent fees onto declined lines; estimate totals omit declined.
    expect($recommended->fresh()->shop_fee_cents)->toBe(1000)
        ->and($declined->fresh()->shop_fee_cents)->toBe(1000)
        ->and((int) $ro->lines()->sum('shop_fee_cents'))->toBe(2000)
        ->and(app(EstimateTotalsCalculator::class)->totalsFor($ro->fresh())->allocatedShopFeesCents())->toBe(1000);
});

test('percentage deferred and draft lines still receive stored fees like main', function () {
    ShopSettings::current()->update(['shop_fee_mode' => 'percent', 'shop_fee_rate' => '10.000', 'shop_fee_cap_cents' => null]);

    $ro = dispositionFeeRo();
    $recommended = dispositionFeeLabor($ro, 'Rec', RepairOrderConcernDisposition::Recommended, 10000);
    $deferred = dispositionFeeLabor($ro, 'Defer', RepairOrderConcernDisposition::Deferred, 10000);
    $draft = dispositionFeeLabor($ro, 'Draft', RepairOrderConcernDisposition::Draft, 10000);

    expect($recommended->fresh()->shop_fee_cents)->toBe(1000)
        ->and($deferred->fresh()->shop_fee_cents)->toBe(1000)
        ->and($draft->fresh()->shop_fee_cents)->toBe(1000)
        ->and(app(EstimateTotalsCalculator::class)->totalsFor($ro->fresh())->allocatedShopFeesCents())->toBe(1000);
});

test('flat per RO with mixed declined still pools across declined lines as main did', function () {
    ShopSettings::current()->update([
        'shop_fee_mode' => 'flat',
        'shop_fee_flat_cents' => 2500,
        'shop_fee_cap_cents' => 1000, // intentional: flat ignores cap on this branch
    ]);

    $ro = dispositionFeeRo();
    $recommended = dispositionFeeLabor($ro, 'Keep', RepairOrderConcernDisposition::Recommended, 10000);
    $declined = dispositionFeeLabor($ro, 'Decline', RepairOrderConcernDisposition::Declined, 10000);

    // Main allocated the flat fee across both eligible lines; estimate shows the recommended share.
    expect($recommended->fresh()->shop_fee_cents)->toBe(1250)
        ->and($declined->fresh()->shop_fee_cents)->toBe(1250)
        ->and((int) $ro->lines()->sum('shop_fee_cents'))->toBe(2500)
        ->and(app(EstimateTotalsCalculator::class)->totalsFor($ro->fresh())->allocatedShopFeesCents())->toBe(1250);
});

test('flat per RO ignores cap while percentage still honors cap', function () {
    ShopSettings::current()->update([
        'shop_fee_mode' => 'flat',
        'shop_fee_flat_cents' => 2500,
        'shop_fee_cap_cents' => 1000,
    ]);
    $flatRo = dispositionFeeRo();
    dispositionFeeLabor($flatRo, 'Flat', RepairOrderConcernDisposition::Recommended, 10000);
    expect((int) $flatRo->lines()->sum('shop_fee_cents'))->toBe(2500);

    ShopSettings::current()->update([
        'shop_fee_mode' => 'percent',
        'shop_fee_rate' => '10.000',
        'shop_fee_cap_cents' => 1000,
    ]);
    $percentRo = dispositionFeeRo();
    dispositionFeeLabor($percentRo, 'A', RepairOrderConcernDisposition::Recommended, 10000);
    dispositionFeeLabor($percentRo, 'B', RepairOrderConcernDisposition::Recommended, 10000);
    expect((int) $percentRo->lines()->sum('shop_fee_cents'))->toBe(1000);
});

test('mixed approved and recommended percentage estimate drops recommended from estimate total like main', function () {
    ShopSettings::current()->update(['shop_fee_mode' => 'percent', 'shop_fee_rate' => '10.000', 'shop_fee_cap_cents' => null]);

    $ro = dispositionFeeRo();
    $approved = dispositionFeeLabor($ro, 'Approved', RepairOrderConcernDisposition::Approved, 10000);
    $recommended = dispositionFeeLabor($ro, 'Recommended', RepairOrderConcernDisposition::Recommended, 10000);

    // Fees are still stored on both lines (main behavior). Estimate billable omits recommended once approved exists.
    expect($approved->fresh()->shop_fee_cents)->toBe(1000)
        ->and($recommended->fresh()->shop_fee_cents)->toBe(1000)
        ->and(app(EstimateTotalsCalculator::class)->totalsFor($ro->fresh())->allocatedShopFeesCents())->toBe(1000);
});
