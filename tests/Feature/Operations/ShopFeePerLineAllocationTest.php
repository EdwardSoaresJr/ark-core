<?php

use App\Ark\Operations\Customers\Customer;
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
        'customer_types' => [
            ...collect(ShopSettings::DEFAULT_CUSTOMER_TYPES)
                ->map(fn (array $row): array => $row['name'] === 'Fleet' ? [...$row, 'shop_fee_rate_override' => '0.000'] : $row)
                ->all(),
            ['name' => 'Rideshare', 'shop_fees_enabled' => true, 'shop_fee_rate_override' => '2.000', 'discount_type' => 'none'],
        ],
        'tax_enabled' => true,
        'default_tax_rate' => '8.000',
        'taxable_labor' => true,
        'taxable_parts' => true,
        'taxable_shop_fees' => false,
        'shop_fee_enabled' => true,
        'shop_fee_rate' => '5.000',
        'shop_fee_cap_cents' => null,
    ]);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));
});

function feeRepairOrder(string $customerType, bool $legacy = false): RepairOrder
{
    $customer = Customer::query()->create(['first_name' => 'Synthetic', 'last_name' => $customerType, 'phone' => '555-0172', 'customer_type' => $customerType]);
    $vehicle = Vehicle::query()->create(['customer_id' => $customer->id, 'year' => 2021, 'make' => 'Toyota', 'model' => 'Camry']);

    return RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Synthetic shop fees',
        ...($legacy ? ['shop_fee_rules' => null] : []),
    ]);
}

function feeConcern(RepairOrder $repairOrder, string $summary, ConcernBillingPosture $posture): RepairOrderConcern
{
    test()->post(route('operations.repair-orders.concerns.store', $repairOrder), ['summary' => $summary])->assertSessionHasNoErrors();

    $concern = RepairOrderConcern::query()->where('repair_order_id', $repairOrder->id)->where('summary', 'like', $summary.'%')->sole();
    $concern->update(['disposition' => RepairOrderConcernDisposition::Recommended, 'billing_posture' => $posture]);

    return $concern->fresh();
}

function feeLabor(RepairOrder $repairOrder, RepairOrderConcern $concern, string $category): RepairOrderLine
{
    $description = 'Labor '.uniqid();

    test()->post(route('operations.repair-orders.lines.store', $repairOrder), [
        'repair_order_concern_id' => $concern->id,
        'type' => 'labor',
        'description' => $description,
        'labor_category_key' => $category,
        'labor_entered_hours' => '2.00',
        'labor_adjustment' => 'normal',
        'labor_rate_overridden' => '0',
    ])->assertSessionHasNoErrors();

    return RepairOrderLine::query()->where('description', $description)->sole();
}

/**
 * @return array<string, RepairOrderLine>
 */
function mixedFeeRepairOrderLines(RepairOrder $repairOrder): array
{
    return [
        'custom_class' => feeLabor($repairOrder, feeConcern($repairOrder, 'Follows Rideshare', ConcernBillingPosture::Default), 'mechanical'),
        'customer_pay' => feeLabor($repairOrder, feeConcern($repairOrder, 'Customer pay', ConcernBillingPosture::CustomerPay), 'mechanical'),
        'repairpal' => feeLabor($repairOrder, feeConcern($repairOrder, 'RepairPal', ConcernBillingPosture::RepairPal), 'repairpal'),
        'warranty' => feeLabor($repairOrder, feeConcern($repairOrder, 'Warranty', ConcernBillingPosture::WarrantyOther), 'warranty-other'),
    ];
}

function feeAmounts(RepairOrderLine $line): array
{
    return $line->fresh()->only(['subtotal_cents', 'shop_fee_cents', 'tax_cents', 'total_cents']);
}

test('a new repair order charges each line the fee of its own billing, and exempt lines nothing', function () {
    $repairOrder = feeRepairOrder('Rideshare');
    $lines = mixedFeeRepairOrderLines($repairOrder);

    expect($repairOrder->fresh()->shop_fee_rules)->toBe(RepairOrder::SHOP_FEES_PER_LINE)
        ->and(feeAmounts($lines['custom_class']))->toBe(['subtotal_cents' => 33000, 'shop_fee_cents' => 660, 'tax_cents' => 2640, 'total_cents' => 36300])
        ->and(feeAmounts($lines['customer_pay']))->toBe(['subtotal_cents' => 33000, 'shop_fee_cents' => 1650, 'tax_cents' => 2640, 'total_cents' => 37290])
        ->and(feeAmounts($lines['repairpal']))->toBe(['subtotal_cents' => 30000, 'shop_fee_cents' => 0, 'tax_cents' => 2400, 'total_cents' => 32400])
        ->and(feeAmounts($lines['warranty']))->toBe(['subtotal_cents' => 33000, 'shop_fee_cents' => 0, 'tax_cents' => 2640, 'total_cents' => 35640])
        ->and(ShopSettings::current()->billingPostureOptionPresentation(ConcernBillingPosture::Default, $repairOrder->fresh())['shop_fee_summary'])->toBe('2% shop fee')
        ->and(ShopSettings::current()->billingPostureOptionPresentation(ConcernBillingPosture::CustomerPay, $repairOrder->fresh())['shop_fee_summary'])->toBe('5% shop fee');
});

test('an exceeded cap reduces fees in proportion to each line fee, and tax follows the capped fee', function () {
    ShopSettings::current()->update(['shop_fee_cap_cents' => 2000, 'taxable_shop_fees' => true]);
    $repairOrder = feeRepairOrder('Rideshare');
    $lines = mixedFeeRepairOrderLines($repairOrder);

    expect(feeAmounts($lines['custom_class']))->toBe(['subtotal_cents' => 33000, 'shop_fee_cents' => 571, 'tax_cents' => 2686, 'total_cents' => 36257])
        ->and(feeAmounts($lines['customer_pay']))->toBe(['subtotal_cents' => 33000, 'shop_fee_cents' => 1429, 'tax_cents' => 2754, 'total_cents' => 37183])
        ->and(feeAmounts($lines['repairpal']))->toBe(['subtotal_cents' => 30000, 'shop_fee_cents' => 0, 'tax_cents' => 2400, 'total_cents' => 32400])
        ->and(feeAmounts($lines['warranty']))->toBe(['subtotal_cents' => 33000, 'shop_fee_cents' => 0, 'tax_cents' => 2640, 'total_cents' => 35640])
        ->and((int) $repairOrder->lines()->sum('shop_fee_cents'))->toBe(2000);
});

test('a cap that is not exceeded changes nothing', function () {
    ShopSettings::current()->update(['shop_fee_cap_cents' => 5000]);
    $repairOrder = feeRepairOrder('Rideshare');
    $lines = mixedFeeRepairOrderLines($repairOrder);

    expect($lines['custom_class']->fresh()->shop_fee_cents)->toBe(660)
        ->and($lines['customer_pay']->fresh()->shop_fee_cents)->toBe(1650);
});

test('adding work at another fee rate never moves an existing line fee on a new repair order', function () {
    $repairOrder = feeRepairOrder('Retail');
    $customerPay = feeLabor($repairOrder, feeConcern($repairOrder, 'Customer pay', ConcernBillingPosture::CustomerPay), 'mechanical');

    $fleet = feeLabor($repairOrder, feeConcern($repairOrder, 'Fleet at zero percent', ConcernBillingPosture::Fleet), 'mechanical');

    expect($customerPay->fresh()->shop_fee_cents)->toBe(1650)
        ->and($fleet->fresh()->shop_fee_cents)->toBe(0);
});

test('a repair order created before per-line fees keeps pooled fees and the shop-wide rule on every recalculation', function () {
    $repairOrder = feeRepairOrder('Rideshare', legacy: true);
    $customerPay = feeLabor($repairOrder, feeConcern($repairOrder, 'Customer pay', ConcernBillingPosture::CustomerPay), 'mechanical');
    $followsClass = feeLabor($repairOrder, feeConcern($repairOrder, 'Follows Rideshare', ConcernBillingPosture::Default), 'mechanical');

    expect($followsClass->fresh()->shop_fee_cents)->toBe(1650)
        ->and($customerPay->fresh()->shop_fee_cents)->toBe(1650);

    $fleet = feeLabor($repairOrder, feeConcern($repairOrder, 'Fleet at zero percent', ConcernBillingPosture::Fleet), 'mechanical');

    expect($repairOrder->fresh()->shop_fee_rules)->toBeNull()
        ->and($fleet->fresh()->shop_fee_cents)->toBe(1100)
        ->and($customerPay->fresh()->shop_fee_cents)->toBe(1100)
        ->and($followsClass->fresh()->shop_fee_cents)->toBe(1100)
        ->and(ShopSettings::current()->billingPostureOptionPresentation(ConcernBillingPosture::Default, $repairOrder->fresh())['shop_fee_summary'])->toBe('5% shop fee');
});

test('a concern moved to a new repair order keeps the fee rules of the repair order it came from', function () {
    foreach ([true, false] as $legacy) {
        $source = feeRepairOrder('Rideshare', legacy: $legacy);
        $concern = feeConcern($source, 'Moves out', ConcernBillingPosture::Default);
        $line = feeLabor($source, $concern, 'mechanical');
        feeConcern($source, 'Stays', ConcernBillingPosture::CustomerPay);

        $this->post(route('operations.repair-orders.concerns.move-to-new-ro', [$source, $concern]), [
            'opened_estimate_version' => $source->fresh()->estimate_version,
        ])->assertRedirect();

        $moved = RepairOrder::query()->findOrFail($concern->fresh()->repair_order_id);

        expect($moved->id)->not->toBe($source->id)
            ->and($moved->shop_fee_rules)->toBe($legacy ? null : RepairOrder::SHOP_FEES_PER_LINE)
            ->and($line->fresh()->shop_fee_cents)->toBe($legacy ? 1650 : 660);
    }
});
