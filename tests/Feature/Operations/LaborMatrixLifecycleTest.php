<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\EstimatePricing\CreateLaborBillingClassAction;
use App\Ark\Operations\EstimatePricing\CreateLaborWorkTypeAction;
use App\Ark\Operations\RepairOrders\ConcernBillingPosture;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\CustomerBillingClassLifecycle;
use App\Ark\Operations\Settings\CustomLaborBillingClassLifecycle;
use App\Ark\Operations\Settings\LaborWorkTypeLifecycle;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\ShopSettingsSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed([ArkAuthorizationSeeder::class, ShopSettingsSeeder::class]);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value));
});

test('unused customer-only billing class can be deleted', function () {
    $settings = ShopSettings::current();
    $created = app(\App\Ark\Operations\Settings\PersistCustomerBillingClasses::class)
        ->create($settings, ['name' => 'Temp Unused Class']);

    $result = app(CustomerBillingClassLifecycle::class)->delete(ShopSettings::current(), $created['key']);

    expect($result['deleted'])->toBeTrue()
        ->and(collect(ShopSettings::current()->customerTypeRows())->firstWhere('key', $created['key']))
        ->toBeNull();
});

test('referenced customer billing class refuses delete and can be archived', function () {
    Customer::query()->create([
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'phone' => '555-0199',
        'customer_type' => 'Military',
    ]);

    $military = collect(ShopSettings::current()->customerTypeRows())->firstWhere('name', 'Military');

    expect(fn () => app(CustomerBillingClassLifecycle::class)->delete(ShopSettings::current(), $military['key']))
        ->toThrow(ValidationException::class);

    $archived = app(CustomerBillingClassLifecycle::class)->archive(ShopSettings::current(), $military['key']);

    expect($archived['is_active'])->toBeFalse()
        ->and(collect(ShopSettings::current()->activeCustomerTypeRows())->firstWhere('key', $military['key']))
        ->toBeNull()
        ->and(collect(ShopSettings::current()->customerTypeRows())->firstWhere('key', $military['key'])['is_active'])
        ->toBeFalse();
});

test('archiving a customer billing class does not remove its linked labor column', function () {
    $wholesale = collect(ShopSettings::current()->customerTypeRows())->firstWhere('name', 'Wholesale');

    app(CustomerBillingClassLifecycle::class)->archive(ShopSettings::current(), $wholesale['key']);

    $matrix = app(\App\Ark\Operations\EstimatePricing\LaborWorkTypeMatrixProjection::class)->build();
    $columnValues = collect($matrix['columns'])->pluck('value')->all();

    expect($columnValues)->toContain('wholesale')
        ->and(collect(ShopSettings::current()->activeCustomerTypeRows())->firstWhere('name', 'Wholesale'))
        ->toBeNull();
});

test('unused custom work type can be archived and deleted', function () {
    $created = app(CreateLaborWorkTypeAction::class)->execute('Lifecycle Temp Type', ShopSettings::current());

    app(LaborWorkTypeLifecycle::class)->archive(ShopSettings::current(), $created['key']);

    expect(collect(ShopSettings::current()->activeLaborCategories())->firstWhere('key', $created['key']))
        ->toBeNull();

    app(LaborWorkTypeLifecycle::class)->restore(ShopSettings::current(), $created['key']);
    $deleted = app(LaborWorkTypeLifecycle::class)->delete(ShopSettings::current(), $created['key']);

    expect($deleted['deleted'])->toBeTrue()
        ->and(collect(ShopSettings::current()->laborCategories())->firstWhere('key', $created['key']))
        ->toBeNull();
});

test('referenced work type refuses delete', function () {
    $customer = Customer::query()->create(['first_name' => 'Pat', 'last_name' => 'Line', 'phone' => '555-0111']);
    $vehicle = Vehicle::query()->create(['customer_id' => $customer->id, 'year' => 2020, 'make' => 'Ford', 'model' => 'Focus']);
    $ro = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Noise',
    ]);
    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $ro->id,
        'summary' => 'Noise',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'billing_posture' => ConcernBillingPosture::CustomerPay,
    ]);
    RepairOrderLine::query()->create([
        'repair_order_id' => $ro->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Diagnose noise',
        'labor_category_key' => 'diagnostic',
        'quantity' => '1.00',
        'unit_price_cents' => 16500,
        'subtotal_cents' => 16500,
        'total_cents' => 16500,
    ]);

    expect(fn () => app(LaborWorkTypeLifecycle::class)->delete(ShopSettings::current(), 'diagnostic'))
        ->toThrow(ValidationException::class);
});

test('default work type cannot be archived', function () {
    expect(fn () => app(LaborWorkTypeLifecycle::class)->archive(ShopSettings::current(), 'mechanical'))
        ->toThrow(ValidationException::class);
});

test('unused custom labor column can be deleted without touching customer classes', function () {
    $created = app(CreateLaborBillingClassAction::class)->execute('Temp Labor Column', 'customer_pay', ShopSettings::current());

    $beforeCustomerCount = count(ShopSettings::current()->customerTypeRows());
    $deleted = app(CustomLaborBillingClassLifecycle::class)->delete(ShopSettings::current(), $created['key']);

    expect($deleted['deleted'])->toBeTrue()
        ->and(count(ShopSettings::current()->customerTypeRows()))->toBe($beforeCustomerCount)
        ->and(collect(ShopSettings::current()->allCustomLaborBillingClasses())->firstWhere('key', $created['key']))
        ->toBeNull();
});

test('manage catalog separates labor-linked actives from customer-only discovery', function () {
    $catalog = app(\App\Ark\Operations\Settings\PersistCustomerBillingClasses::class)
        ->catalog(ShopSettings::current());

    $manageKeys = collect($catalog['manage_classes'])->pluck('key')->all();

    expect($manageKeys)->toContain('military')
        ->and($manageKeys)->toContain('business')
        ->and($manageKeys)->not->toContain('retail')
        ->and($manageKeys)->not->toContain('warranty');
});
