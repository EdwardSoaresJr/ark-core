<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\EstimatePricing\CanonicalBillingClassCatalog;
use App\Ark\Operations\EstimatePricing\LaborPolicy;
use App\Ark\Operations\EstimatePricing\LaborWorkTypeMatrixProjection;
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
use Database\Seeders\RepairOrderStatusCatalogSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
    ShopSettings::current()->update([
        'customer_types' => ShopSettings::DEFAULT_CUSTOMER_TYPES,
        'labor_categories' => ShopSettings::DEFAULT_LABOR_CATEGORIES,
        'default_labor_rate_cents' => 16500,
        'tax_enabled' => false,
        'shop_fee_enabled' => false,
    ]);
    $this->advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $this->admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
    $this->actingAs($this->advisor);
});

function canonicalRepairOrder(string $classKey): RepairOrder
{
    $customer = Customer::query()->create([
        'first_name' => 'Synthetic',
        'last_name' => 'Canonical',
        'phone' => '555-0217',
        'billing_class_key' => $classKey,
    ]);
    $vehicle = Vehicle::query()->create(['customer_id' => $customer->id, 'year' => 2021, 'make' => 'Ford', 'model' => 'Transit']);

    return RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Canonical billing',
    ]);
}

function canonicalConcern(RepairOrder $repairOrder, string $summary): RepairOrderConcern
{
    test()->post(route('operations.repair-orders.concerns.store', $repairOrder), ['summary' => $summary])->assertSessionHasNoErrors();

    $concern = RepairOrderConcern::query()->where('repair_order_id', $repairOrder->id)->where('summary', 'like', $summary.'%')->sole();
    $concern->update(['disposition' => RepairOrderConcernDisposition::Recommended]);

    return $concern->fresh();
}

function canonicalLabor(RepairOrder $repairOrder, RepairOrderConcern $concern): RepairOrderLine
{
    $description = 'Labor '.uniqid();

    test()->post(route('operations.repair-orders.lines.store', $repairOrder), [
        'repair_order_concern_id' => $concern->id,
        'type' => 'labor',
        'description' => $description,
        'labor_category_key' => 'mechanical',
        'labor_entered_hours' => '2.00',
        'labor_adjustment' => 'normal',
        'labor_rate_overridden' => '0',
    ])->assertSessionHasNoErrors();

    return RepairOrderLine::query()->where('description', $description)->sole();
}

function canonicalSetBilling(RepairOrder $repairOrder, RepairOrderConcern $concern, string $value): void
{
    test()->patch(route('operations.repair-orders.concerns.billing-posture', [$repairOrder, $concern]), [
        'billing_posture' => $value,
    ])->assertSessionHasNoErrors();
}

test('a class created once appears for customers, concern overrides, the labor matrix and commercial rules', function () {
    $this->actingAs($this->admin)
        ->postJson(route('operations.settings.shop.customer-types.store'), [
            'name' => 'Insurance',
            'shop_fees_enabled' => true,
            'discount_type' => 'none',
        ])
        ->assertCreated()
        ->assertJsonPath('class.key', 'insurance')
        ->assertJsonPath('class.labor_column_key', 'custom_insurance');

    $settings = ShopSettings::current();
    $customerOption = collect(CanonicalBillingClassCatalog::selectableForCustomers($settings))->firstWhere('key', 'insurance');
    $concernOption = collect(CanonicalBillingClassCatalog::selectableForConcerns($settings))->firstWhere('key', 'insurance');
    $matrixColumns = collect(app(LaborWorkTypeMatrixProjection::class)->build()['columns']);
    $commercialRules = collect($this->getJson(route('operations.settings.shop.customer-types.show'))->assertOk()->json('classes'));

    expect($customerOption)->toMatchArray(['name' => 'Insurance', 'labor_column_key' => 'custom_insurance'])
        ->and($concernOption)->toMatchArray(['label' => 'Insurance', 'labor_column_key' => 'custom_insurance'])
        ->and($matrixColumns->firstWhere('value', 'custom_insurance')['label'])->toBe('Insurance')
        ->and($commercialRules->firstWhere('key', 'insurance')['labor_column_key'])->toBe('custom_insurance')
        ->and(LaborPolicy::query()->where('billing_posture', 'custom_insurance')->exists())->toBeTrue();
});

test('adding from the labor matrix creates the same full billing class, not a labor-only column', function () {
    $this->actingAs($this->admin)
        ->postJson(route('operations.settings.shop.labor-matrix.billing-classes.store'), [
            'name' => 'Rideshare',
            'base_posture' => 'customer_pay',
        ])
        ->assertOk()
        ->assertJsonPath('billing_class.key', 'custom_rideshare')
        ->assertJsonPath('customer_class.key', 'rideshare');

    expect(collect(CanonicalBillingClassCatalog::selectableForCustomers(ShopSettings::current()))->pluck('key'))->toContain('rideshare');
});

test('a new repair order under a Fleet customer has concerns that inherit Fleet and no visit billing control', function () {
    $repairOrder = canonicalRepairOrder('fleet');
    $first = canonicalConcern($repairOrder, 'Brakes');
    $second = canonicalConcern($repairOrder, 'Alignment');

    expect($first->inheritsCustomerBilling())->toBeTrue()
        ->and($second->inheritsCustomerBilling())->toBeTrue()
        ->and($first->effectiveBillingClassKey())->toBe('fleet')
        ->and($second->effectiveBillingClassKey())->toBe('fleet')
        ->and($repairOrder->fresh()->hasOwnBillingPosture())->toBeFalse();

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Fleet (Customer default)')
        ->assertDontSee('data-visit-billing', false)
        ->assertDontSee('visit-billing', false);
});

test('overriding one concern leaves siblings and the customer alone and can be cleared back to the customer class', function () {
    $repairOrder = canonicalRepairOrder('fleet');
    $overridden = canonicalConcern($repairOrder, 'Warranty brakes');
    $sibling = canonicalConcern($repairOrder, 'Fleet alignment');

    canonicalSetBilling($repairOrder, $overridden, 'warranty_other');

    expect($overridden->fresh()->inheritsCustomerBilling())->toBeFalse()
        ->and($overridden->fresh()->effectiveBillingClassKey())->toBe('warranty_other')
        ->and($overridden->fresh()->billingClassSourceLabel())->toBe('Warranty (Override)')
        ->and($sibling->fresh()->effectiveBillingClassKey())->toBe('fleet')
        ->and($sibling->fresh()->inheritsCustomerBilling())->toBeTrue()
        ->and($repairOrder->customer->fresh()->billingClassKey())->toBe('fleet');

    canonicalSetBilling($repairOrder, $overridden, 'inherit');

    expect($overridden->fresh()->inheritsCustomerBilling())->toBeTrue()
        ->and($overridden->fresh()->billingClassKey())->toBe('default')
        ->and($overridden->fresh()->effectiveBillingClassKey())->toBe('fleet')
        ->and($repairOrder->customer->fresh()->billingClassKey())->toBe('fleet');
});

test('an override prices labor at its own class rate while the inheriting concern stays at the customer class rate', function () {
    $repairOrder = canonicalRepairOrder('retail');
    $retail = canonicalConcern($repairOrder, 'Retail brakes');
    $repairPal = canonicalConcern($repairOrder, 'RepairPal brakes');

    $retailLine = canonicalLabor($repairOrder, $retail);
    $repairPalLine = canonicalLabor($repairOrder, $repairPal);
    canonicalSetBilling($repairOrder, $repairPal, 'repairpal');

    $retailRate = (int) $retailLine->fresh()->unit_price_cents;
    $repairPalRate = (int) $repairPalLine->fresh()->unit_price_cents;

    expect($retailRate)->toBe(16500)
        ->and($repairPalRate)->toBe(15000)
        ->and($repairPalRate)->not->toBe($retailRate)
        ->and($repairPalLine->fresh()->resolved_from_posture)->toBe('repairpal')
        ->and($retail->fresh()->inheritsCustomerBilling())->toBeTrue();
});

test('changing the customer from Retail to Fleet reprices inherited concerns and preserves overrides', function () {
    LaborPolicy::query()->where('billing_posture', 'fleet')->update(['hourly_rate_cents' => 14000]);

    $repairOrder = canonicalRepairOrder('retail');
    $inherited = canonicalConcern($repairOrder, 'Inherited brakes');
    $overridden = canonicalConcern($repairOrder, 'Warranty brakes');
    $inheritedLine = canonicalLabor($repairOrder, $inherited);
    $overriddenLine = canonicalLabor($repairOrder, $overridden);
    canonicalSetBilling($repairOrder, $overridden, 'warranty_other');
    $overriddenRate = (int) $overriddenLine->fresh()->unit_price_cents;

    expect((int) $inheritedLine->fresh()->unit_price_cents)->toBe(16500);

    $customer = $repairOrder->customer;
    $this->patch(route('operations.customers.update', $customer), [
        'first_name' => $customer->first_name,
        'last_name' => $customer->last_name,
        'billing_class_key' => 'fleet',
    ])->assertSessionHasNoErrors();

    expect($customer->fresh()->customer_type)->toBe('Fleet')
        ->and($customer->fresh()->billing_class_key)->toBe('fleet')
        ->and((int) $inheritedLine->fresh()->unit_price_cents)->toBe(14000)
        ->and($inherited->fresh()->effectiveBillingClassKey())->toBe('fleet')
        ->and($overridden->fresh()->effectiveBillingClassKey())->toBe('warranty_other')
        ->and((int) $overriddenLine->fresh()->unit_price_cents)->toBe($overriddenRate);
});

test('a customer class change never raises an approved line rate', function () {
    LaborPolicy::query()->where('billing_posture', 'fleet')->update(['hourly_rate_cents' => 20000]);

    $repairOrder = canonicalRepairOrder('retail');
    $concern = canonicalConcern($repairOrder, 'Approved brakes');
    $line = canonicalLabor($repairOrder, $concern);
    $concern->update(['disposition' => RepairOrderConcernDisposition::Approved]);
    approveCurrentAmounts($repairOrder->fresh());
    $approvedRate = (int) $line->fresh()->unit_price_cents;

    $customer = $repairOrder->customer;
    $this->patch(route('operations.customers.update', $customer), [
        'first_name' => $customer->first_name,
        'last_name' => $customer->last_name,
        'billing_class_key' => 'fleet',
    ])->assertSessionHasNoErrors();

    expect((int) $line->fresh()->unit_price_cents)->toBe($approvedRate)
        ->and($concern->fresh()->effectiveBillingClassKey())->toBe('fleet');
});

test('legacy default concerns inherit while non-default legacy concerns stay explicit overrides', function () {
    $repairOrder = canonicalRepairOrder('fleet');
    $legacyDefault = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Legacy default',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'position' => 1,
    ]);
    $legacyOverride = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Legacy warranty',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'position' => 2,
        'billing_posture' => 'warranty_other',
    ]);
    \Illuminate\Support\Facades\DB::table('repair_order_concerns')->update(['billing_inherits_customer' => null]);

    expect($legacyDefault->fresh()->inheritsCustomerBilling())->toBeTrue()
        ->and($legacyDefault->fresh()->effectiveBillingClassKey())->toBe('fleet')
        ->and($legacyOverride->fresh()->inheritsCustomerBilling())->toBeFalse()
        ->and($legacyOverride->fresh()->effectiveBillingClassKey())->toBe('warranty_other');
});

test('a name collision with a labor-only column is reported and both are preserved', function () {
    ShopSettings::current()->update([
        'custom_labor_billing_classes' => [['key' => 'custom_business', 'label' => 'Business', 'base_posture' => 'customer_pay', 'is_active' => true]],
    ]);

    $result = app(\App\Ark\Operations\EstimatePricing\CreateCanonicalBillingClassAction::class)
        ->linkLegacyCustomerOnlyClasses(ShopSettings::current());

    expect(collect($result['collisions'])->pluck('class_key')->all())->toBe(['business'])
        ->and(collect($result['linked'])->pluck('class_key')->all())->toBe(['military'])
        ->and(collect(ShopSettings::current()->allCustomLaborBillingClasses())->pluck('key')->all())
        ->toContain('custom_business', 'custom_military');
});

test('an unresolved collision class cannot be assigned to a customer and would otherwise price at Retail', function () {
    ShopSettings::current()->update([
        'custom_labor_billing_classes' => [['key' => 'custom_business', 'label' => 'Business', 'base_posture' => 'customer_pay', 'is_active' => true]],
    ]);
    app(\App\Ark\Operations\EstimatePricing\CreateCanonicalBillingClassAction::class)
        ->linkLegacyCustomerOnlyClasses(ShopSettings::current());

    $settings = ShopSettings::current();
    $business = collect($settings->customerTypeRows())->firstWhere('key', 'business');
    $military = collect($settings->customerTypeRows())->firstWhere('key', 'military');

    expect($business['labor_column_key'] ?? null)->toBeNull()
        ->and(CanonicalBillingClassCatalog::laborColumnForClassRow($business))->toBe('customer_pay')
        ->and(CanonicalBillingClassCatalog::isAssignableToCustomer($business, $settings))->toBeFalse()
        ->and(CanonicalBillingClassCatalog::isAssignableToCustomer($military, $settings))->toBeTrue()
        ->and(collect(CanonicalBillingClassCatalog::selectableForCustomers($settings))->pluck('key'))
        ->not->toContain('business')
        ->and(collect(CanonicalBillingClassCatalog::selectableForCustomers($settings))->pluck('key'))
        ->toContain('military');

    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value))
        ->post(route('operations.customers.store'), [
            'first_name' => 'Blocked',
            'last_name' => 'Business',
            'phone' => '555-0199',
            'customer_type' => 'Business',
        ])
        ->assertSessionHasErrors('customer_type');
});

test('a closed repair order refuses concern billing changes and keeps its stored labor rate', function () {
    $repairOrder = canonicalRepairOrder('warranty');
    $concern = canonicalConcern($repairOrder, 'Closed brakes');
    $line = canonicalLabor($repairOrder, $concern);
    $rate = (int) $line->unit_price_cents;

    $repairOrder->update([
        'status' => RepairOrderStatus::Closed,
        'closed_at' => now(),
    ]);

    $this->patch(route('operations.repair-orders.concerns.billing-posture', [$repairOrder, $concern]), [
        'billing_posture' => 'repairpal',
        \App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD => (int) $repairOrder->fresh()->estimate_version,
    ])->assertStatus(423);

    expect($concern->fresh()->effectiveBillingClassKey())->toBe('warranty_other')
        ->and((int) $line->fresh()->unit_price_cents)->toBe($rate);
});
