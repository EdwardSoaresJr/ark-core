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
            ...ShopSettings::DEFAULT_CUSTOMER_TYPES,
            ['name' => 'Insurance', 'shop_fees_enabled' => true, 'discount_type' => 'labor', 'discount_amount' => '5.00', 'concern_billing_posture' => 'warranty_other'],
            ['name' => 'Rideshare', 'shop_fees_enabled' => true, 'discount_type' => 'none'],
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

function classSettingsRepairOrder(string $customerType): RepairOrder
{
    $customer = Customer::query()->create(['first_name' => 'Synthetic', 'last_name' => $customerType, 'phone' => '555-0171', 'customer_type' => $customerType]);
    $vehicle = Vehicle::query()->create(['customer_id' => $customer->id, 'year' => 2022, 'make' => 'Subaru', 'model' => 'Outback']);

    return RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Synthetic billing class',
    ]);
}

function classSettingsConcern(RepairOrder $repairOrder, string $summary, ?ConcernBillingPosture $posture = null): RepairOrderConcern
{
    test()->post(route('operations.repair-orders.concerns.store', $repairOrder), ['summary' => $summary])->assertSessionHasNoErrors();

    $concern = RepairOrderConcern::query()->where('repair_order_id', $repairOrder->id)->where('summary', 'like', $summary.'%')->sole();
    $concern->update(['disposition' => RepairOrderConcernDisposition::Recommended, ...($posture !== null ? ['billing_posture' => $posture] : [])]);

    return $concern->fresh();
}

function classSettingsLabor(RepairOrder $repairOrder, RepairOrderConcern $concern, string $category): RepairOrderLine
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

function classSettingsPart(RepairOrder $repairOrder, RepairOrderConcern $concern, string $cost): RepairOrderLine
{
    $description = 'Part '.uniqid();

    test()->post(route('operations.repair-orders.lines.store', $repairOrder), [
        'repair_order_concern_id' => $concern->id,
        'type' => 'part',
        'description' => $description,
        'quantity' => '1.00',
        'part_cost' => $cost,
        'pricing_mode' => 'matrix',
    ])->assertSessionHasNoErrors();

    return RepairOrderLine::query()->where('description', $description)->sole();
}

test('a custom billing class can name the billing its new concerns start with', function () {
    $insured = classSettingsRepairOrder('Insurance');
    $concern = classSettingsConcern($insured, 'Insured collision repair');
    $labor = classSettingsLabor($insured, $concern, 'warranty-other');

    expect($concern->billing_posture)->toBe(ConcernBillingPosture::WarrantyOther)
        ->and($insured->fresh()->defaultBillingPosture())->toBe(ConcernBillingPosture::WarrantyOther)
        ->and($labor->fresh())
        ->unit_price_cents->toBe(16500)
        ->resolved_from_posture->toBe('warranty_other')
        ->standing_discount_cents->toBe(0)
        ->shop_fee_cents->toBe(0)
        ->and($concern->defaultPartsMatrix()['key'])->toBe('warranty-no-markup');

    $rideshare = classSettingsRepairOrder('Rideshare');
    expect(classSettingsConcern($rideshare, 'Rideshare brakes')->billing_posture)->toBe(ConcernBillingPosture::Default);
});

test('saving which billing a class starts concerns with leaves existing concerns alone', function () {
    $rideshare = classSettingsRepairOrder('Rideshare');
    $existing = classSettingsConcern($rideshare, 'Existing rideshare concern');

    $rows = collect(ShopSettings::current()->storedCustomerTypeRows())
        ->map(fn (array $row): array => $row['name'] === 'Rideshare' ? [...$row, 'concern_billing_posture' => 'fleet'] : $row)
        ->values()
        ->all();
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value));

    $this->patch(route('operations.settings.shop.customer-types.update'), ['customer_types' => $rows])->assertSessionHasNoErrors();

    expect(collect(ShopSettings::current()->customerTypeRows())->firstWhere('name', 'Rideshare')['concern_billing_posture'])->toBe('fleet')
        ->and($existing->fresh()->billing_posture)->toBe(ConcernBillingPosture::Default)
        ->and(classSettingsConcern($rideshare, 'New rideshare concern')->billing_posture)->toBe(ConcernBillingPosture::Fleet);

    $this->get(route('operations.settings.shop.edit', ['section' => 'financial', 'financial-tab' => 'labor']))
        ->assertOk()
        ->assertSee('data-billing-classes-shared', false)
        ->assertSee('Manage Billing Classes')
        ->assertSee('New concerns bill as', false)
        ->assertSee('concern_billing_posture', false);

    $rows[0]['concern_billing_posture'] = 'warranty_repairpal';
    $this->patch(route('operations.settings.shop.customer-types.update'), ['customer_types' => $rows])
        ->assertSessionHasErrors('customer_types.0.concern_billing_posture');
});

test('a concern on shop default billing uses its customer billing class parts matrix', function () {
    ShopSettings::current()->update([
        'customer_types' => collect(ShopSettings::current()->storedCustomerTypeRows())
            ->map(fn (array $row): array => $row['name'] === 'Military' ? [...$row, 'default_parts_matrix_key' => 'oem-parts'] : $row)
            ->all(),
    ]);
    $settings = ShopSettings::current();
    $repairOrder = classSettingsRepairOrder('Military');
    $followsClass = classSettingsConcern($repairOrder, 'Follows Military');
    $customerPay = classSettingsConcern($repairOrder, 'Customer pay', ConcernBillingPosture::CustomerPay);

    $followsClassPart = classSettingsPart($repairOrder, $followsClass, '50.00');
    $customerPayPart = classSettingsPart($repairOrder, $customerPay, '50.00');

    expect($settings->defaultPartsMatrix()['key'])->not->toBe('oem-parts')
        ->and($followsClassPart->fresh()->pricing_matrix_key)->toBe('oem-parts')
        ->and($customerPayPart->fresh()->pricing_matrix_key)->toBe($settings->defaultPartsMatrix()['key']);
});

test('a Military customer pays the labor discount and shop fee on Customer Pay work only, not on RepairPal work', function () {
    $repairOrder = classSettingsRepairOrder('Military');
    $followsClass = classSettingsConcern($repairOrder, 'Follows Military');
    $customerPay = classSettingsConcern($repairOrder, 'Customer pay', ConcernBillingPosture::CustomerPay);
    $repairPal = classSettingsConcern($repairOrder, 'RepairPal', ConcernBillingPosture::RepairPal);
    $warranty = classSettingsConcern($repairOrder, 'Warranty', ConcernBillingPosture::WarrantyOther);

    $lines = [
        'follows' => classSettingsLabor($repairOrder, $followsClass, 'mechanical'),
        'customer_pay' => classSettingsLabor($repairOrder, $customerPay, 'mechanical'),
        'repairpal' => classSettingsLabor($repairOrder, $repairPal, 'repairpal'),
        'warranty' => classSettingsLabor($repairOrder, $warranty, 'warranty-other'),
    ];
    $this->post(route('operations.repair-orders.lines.store', $repairOrder), [
        'repair_order_concern_id' => $customerPay->id, 'type' => 'part', 'description' => 'Customer pay part',
        'quantity' => '1.00', 'part_cost' => '50.00', 'pricing_mode' => 'manual', 'unit_price' => '100.00', 'unit_price_override' => '1',
    ])->assertSessionHasNoErrors();
    $part = RepairOrderLine::query()->where('description', 'Customer pay part')->sole();

    $amounts = fn (RepairOrderLine $line): array => $line->fresh()->only(['unit_price_cents', 'subtotal_cents', 'standing_discount_cents', 'shop_fee_cents', 'tax_cents', 'total_cents']);

    expect($amounts($lines['follows']))->toBe(['unit_price_cents' => 16500, 'subtotal_cents' => 33000, 'standing_discount_cents' => 3300, 'shop_fee_cents' => 1485, 'tax_cents' => 2376, 'total_cents' => 33561])
        ->and($amounts($lines['customer_pay']))->toBe(['unit_price_cents' => 16500, 'subtotal_cents' => 33000, 'standing_discount_cents' => 3300, 'shop_fee_cents' => 1485, 'tax_cents' => 2376, 'total_cents' => 33561])
        ->and($amounts($part))->toBe(['unit_price_cents' => 10000, 'subtotal_cents' => 10000, 'standing_discount_cents' => 0, 'shop_fee_cents' => 500, 'tax_cents' => 800, 'total_cents' => 11300])
        ->and($amounts($lines['repairpal']))->toBe(['unit_price_cents' => 15000, 'subtotal_cents' => 30000, 'standing_discount_cents' => 0, 'shop_fee_cents' => 0, 'tax_cents' => 2400, 'total_cents' => 32400])
        ->and($amounts($lines['warranty']))->toBe(['unit_price_cents' => 16500, 'subtotal_cents' => 33000, 'standing_discount_cents' => 0, 'shop_fee_cents' => 0, 'tax_cents' => 2640, 'total_cents' => 35640]);
});
