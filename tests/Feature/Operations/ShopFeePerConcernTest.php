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
        'shop_fee_mode' => 'per_concern',
        'shop_fee_rate' => '5.000',
        'shop_fee_flat_cents' => 1500,
        'shop_fee_cap_cents' => null,
        'taxable_shop_fees' => false,
    ]);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value));
});

function perConcernFeeRepairOrder(string $customerType = 'Retail'): RepairOrder
{
    $customer = Customer::query()->create([
        'first_name' => 'Synthetic',
        'last_name' => 'PerConcernFee',
        'phone' => '555-0188',
        'customer_type' => $customerType,
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2019,
        'make' => 'Toyota',
        'model' => 'Corolla',
    ]);

    return RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Synthetic per-concern shop fee',
    ]);
}

function perConcernFeeLabor(
    RepairOrder $repairOrder,
    string $summary,
    ConcernBillingPosture $posture = ConcernBillingPosture::CustomerPay,
    RepairOrderConcernDisposition $disposition = RepairOrderConcernDisposition::Recommended,
    int $lineCount = 1,
): RepairOrderConcern {
    test()->post(route('operations.repair-orders.concerns.store', $repairOrder), [
        'summary' => $summary,
    ])->assertSessionHasNoErrors();

    $concern = RepairOrderConcern::query()
        ->where('repair_order_id', $repairOrder->id)
        ->where('summary', 'like', $summary.'%')
        ->sole();
    $concern->update([
        'disposition' => $disposition,
        'billing_posture' => $posture,
    ]);

    for ($i = 0; $i < $lineCount; $i++) {
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
    }

    return $concern->fresh();
}

function configureShopFee(string $mode, ?string $rate, ?string $flat, ?string $cap): void
{
    test()->patch(route('operations.settings.shop.fees.update'), array_filter([
        'shop_fee_enabled' => '1',
        'shop_fee_mode' => $mode,
        'shop_fee_rate' => $rate,
        'shop_fee_flat' => $flat,
        'shop_fee_cap' => $cap,
    ], fn ($value) => $value !== null))->assertRedirect()->assertSessionHasNoErrors();
}

test('per-concern fee matrix matches owner examples', function (
    string $mode,
    ?string $rate,
    ?string $flat,
    ?string $cap,
    int $eligibleConcerns,
    int $linesPerConcern,
    int $expectedFeeCents,
) {
    configureShopFee($mode, $rate, $flat, $cap);

    $repairOrder = perConcernFeeRepairOrder();

    for ($i = 0; $i < $eligibleConcerns; $i++) {
        perConcernFeeLabor($repairOrder, 'Eligible '.$i, ConcernBillingPosture::CustomerPay, RepairOrderConcernDisposition::Recommended, $linesPerConcern);
    }

    expect((int) $repairOrder->lines()->sum('shop_fee_cents'))->toBe($expectedFeeCents)
        ->and($repairOrder->fresh()->shop_fee_profile['mode'])->toBe($mode);
})->with([
    'percent 5% of 165 under 40 cap' => ['percent', '5.000', null, '40.00', 1, 1, 825],
    'flat per RO with 1 concern' => ['flat', null, '25.00', null, 1, 1, 2500],
    'flat per RO with 4 concerns' => ['flat', null, '25.00', null, 4, 1, 2500],
    'per concern 15 no cap 1' => ['per_concern', null, '15.00', null, 1, 1, 1500],
    'per concern 15 no cap 3' => ['per_concern', null, '15.00', null, 3, 1, 4500],
    'per concern 15 capped 40 over 3' => ['per_concern', null, '15.00', '40.00', 3, 1, 4000],
    'per concern 15 capped 45 over 4' => ['per_concern', null, '15.00', '45.00', 4, 1, 4500],
    'per concern 15 capped 40 with 0 eligible' => ['per_concern', null, '15.00', '40.00', 0, 1, 0],
]);

test('per-concern fee counts a multi-line concern once', function () {
    configureShopFee('per_concern', null, '15.00', null);

    $repairOrder = perConcernFeeRepairOrder();
    perConcernFeeLabor($repairOrder, 'Multi-line', ConcernBillingPosture::CustomerPay, RepairOrderConcernDisposition::Recommended, 3);

    expect($repairOrder->lines()->count())->toBe(3)
        ->and((int) $repairOrder->lines()->sum('shop_fee_cents'))->toBe(1500);
});

test('declined concerns do not increase the per-concern shop fee', function () {
    configureShopFee('per_concern', null, '15.00', '40.00');

    $repairOrder = perConcernFeeRepairOrder();
    perConcernFeeLabor($repairOrder, 'Approved A', ConcernBillingPosture::CustomerPay, RepairOrderConcernDisposition::Approved);
    perConcernFeeLabor($repairOrder, 'Approved B', ConcernBillingPosture::CustomerPay, RepairOrderConcernDisposition::Approved);
    perConcernFeeLabor($repairOrder, 'Declined C', ConcernBillingPosture::CustomerPay, RepairOrderConcernDisposition::Declined);
    perConcernFeeLabor($repairOrder, 'Declined D', ConcernBillingPosture::CustomerPay, RepairOrderConcernDisposition::Declined);

    // Living estimate totals exclude declined dispositions; fee uses the same eligible line set.
    $totals = app(EstimateTotalsCalculator::class)->totalsFor($repairOrder->fresh());

    expect($totals->allocatedShopFeesCents())->toBe(3000);
});

test('warranty posture concerns are not fee-eligible for per-concern fees', function () {
    configureShopFee('per_concern', null, '15.00', null);

    $repairOrder = perConcernFeeRepairOrder();
    perConcernFeeLabor($repairOrder, 'Warranty', ConcernBillingPosture::WarrantyOther);

    expect((int) $repairOrder->lines()->sum('shop_fee_cents'))->toBe(0);
});

test('changing per-concern settings does not reprice an existing stamped repair order', function () {
    configureShopFee('per_concern', null, '15.00', null);

    $repairOrder = perConcernFeeRepairOrder();
    perConcernFeeLabor($repairOrder, 'Locked');
    expect((int) $repairOrder->lines()->sum('shop_fee_cents'))->toBe(1500);

    configureShopFee('per_concern', null, '25.00', '40.00');

    app(EstimateTotalsCalculator::class)->ensureFinancialTotalsAreCurrent($repairOrder->fresh());

    expect((int) $repairOrder->lines()->sum('shop_fee_cents'))->toBe(1500)
        ->and($repairOrder->fresh()->shop_fee_profile['flat_cents'])->toBe(1500)
        ->and($repairOrder->fresh()->shop_fee_profile['mode'])->toBe('per_concern');

    $newer = perConcernFeeRepairOrder();
    perConcernFeeLabor($newer, 'Uses new fee');
    expect((int) $newer->lines()->sum('shop_fee_cents'))->toBe(2500)
        ->and($newer->fresh()->shop_fee_profile['flat_cents'])->toBe(2500);
});

test('flat per RO ignores a configured cap', function () {
    ShopSettings::current()->update([
        'shop_fee_mode' => 'flat',
        'shop_fee_flat_cents' => 5000,
        'shop_fee_cap_cents' => 1000,
    ]);

    $repairOrder = perConcernFeeRepairOrder();
    perConcernFeeLabor($repairOrder, 'Flat ignores cap');

    expect((int) $repairOrder->lines()->sum('shop_fee_cents'))->toBe(5000)
        ->and($repairOrder->fresh()->shop_fee_profile['mode'])->toBe('flat');
});

test('switching fee methods preserves inactive stored values', function () {
    ShopSettings::current()->update([
        'shop_fee_enabled' => true,
        'shop_fee_mode' => 'percent',
        'shop_fee_rate' => '7.000',
        'shop_fee_flat_cents' => 2200,
        'shop_fee_cap_cents' => 5500,
    ]);

    $this->patch(route('operations.settings.shop.fees.update'), [
        'shop_fee_enabled' => '1',
        'shop_fee_mode' => 'flat',
        'shop_fee_flat' => '30.00',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $settings = ShopSettings::current()->fresh();
    expect($settings->shop_fee_mode)->toBe('flat')
        ->and($settings->shop_fee_flat_cents)->toBe(3000)
        ->and((string) $settings->shop_fee_rate)->toBe('7.000')
        ->and($settings->shop_fee_cap_cents)->toBe(5500);

    $this->patch(route('operations.settings.shop.fees.update'), [
        'shop_fee_enabled' => '1',
        'shop_fee_mode' => 'per_concern',
        'shop_fee_flat' => '12.00',
        'shop_fee_cap' => '35.00',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $settings = ShopSettings::current()->fresh();
    expect($settings->shop_fee_mode)->toBe('per_concern')
        ->and($settings->shop_fee_flat_cents)->toBe(1200)
        ->and($settings->shop_fee_cap_cents)->toBe(3500)
        ->and((string) $settings->shop_fee_rate)->toBe('7.000');
});

test('shop fee profile marks per-concern mode and schema 3 with applies_to', function () {
    $profile = ShopFeeProfile::fromShopSettings(ShopSettings::current());

    expect($profile->isPerConcern())->toBeTrue()
        ->and($profile->usesCap())->toBeTrue()
        ->and($profile->toArray()['schema'])->toBe(3)
        ->and($profile->toArray()['applies_to'])->toBe('both')
        ->and($profile->toArray()['mode'])->toBe('per_concern');
});

test('percent capped matrix example uses a 1000 dollar eligible base', function () {
    configureShopFee('percent', '5.000', null, '40.00');

    $repairOrder = perConcernFeeRepairOrder();
    $concern = perConcernFeeLabor($repairOrder, 'Big ticket');
    $line = RepairOrderLine::query()->where('repair_order_concern_id', $concern->id)->sole();
    $line->update([
        'unit_price_cents' => 100000,
        'subtotal_cents' => 100000,
        'total_cents' => 100000,
    ]);

    app(EstimateTotalsCalculator::class)->ensureFinancialTotalsAreCurrent($repairOrder->fresh());

    expect((int) $repairOrder->lines()->sum('shop_fee_cents'))->toBe(4000);
});
