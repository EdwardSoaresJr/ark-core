<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Events\OperationalEvent;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\RepairOrders\ConcernBillingPosture;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderConcurrency;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
    ShopSettings::current()->update([
        'labor_categories' => ShopSettings::DEFAULT_LABOR_CATEGORIES,
        'default_labor_rate_cents' => 16500,
        'customer_types' => ShopSettings::DEFAULT_CUSTOMER_TYPES,
        'tax_enabled' => false,
        'shop_fee_enabled' => true,
        'shop_fee_rate' => '5.000',
        'shop_fee_cap_cents' => null,
    ]);
    $this->advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $this->actingAs($this->advisor);
});

function visitBillingRepairOrder(Customer $customer, RepairOrderStatus $status = RepairOrderStatus::Estimate): RepairOrder
{
    $vehicle = Vehicle::query()->create(['customer_id' => $customer->id, 'year' => 2021, 'make' => 'Ford', 'model' => 'Escape']);

    return RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => $status,
        'concern_summary' => 'Synthetic visit billing',
    ]);
}

function visitBillingConcern(RepairOrder $repairOrder, string $summary, ?ConcernBillingPosture $posture = null): RepairOrderConcern
{
    test()->post(route('operations.repair-orders.concerns.store', $repairOrder), ['summary' => $summary])->assertSessionHasNoErrors();

    $concern = RepairOrderConcern::query()->where('repair_order_id', $repairOrder->id)->where('summary', 'like', $summary.'%')->sole();
    $concern->update(['disposition' => RepairOrderConcernDisposition::Recommended]);

    if ($posture !== null) {
        $concern->update(['billing_posture' => $posture]);
    }

    return $concern->fresh();
}

function visitBillingLabor(RepairOrder $repairOrder, RepairOrderConcern $concern, string $category): RepairOrderLine
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

function visitBillingApply(RepairOrder $repairOrder, ConcernBillingPosture $posture): TestResponse
{
    return test()->patch(route('operations.repair-orders.billing-posture.update', $repairOrder), [
        'billing_posture' => $posture->value,
        RepairOrderConcurrency::FIELD => (int) $repairOrder->fresh()->estimate_version,
    ]);
}

test('a repair order has no billing control and its retired routes refuse changes', function () {
    $customer = Customer::query()->create(['first_name' => 'Synthetic', 'last_name' => 'Warranty', 'phone' => '555-0161', 'customer_type' => 'Warranty']);
    $repairOrder = visitBillingRepairOrder($customer);
    $concern = visitBillingConcern($repairOrder, 'Brakes');

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertDontSee('data-visit-billing', false)
        ->assertDontSee('data-workspace-modal-form="visit-billing"', false)
        ->assertDontSee('Billing for this visit');

    visitBillingApply($repairOrder, ConcernBillingPosture::RepairPal)->assertRedirect();
    $this->getJson(route('operations.repair-orders.billing-posture.preview', [$repairOrder, 'billing_posture' => 'fleet']))->assertStatus(410);
    $this->patchJson(route('operations.repair-orders.billing-posture.update', $repairOrder), ['billing_posture' => 'fleet'])->assertStatus(410);

    expect($repairOrder->fresh()->hasOwnBillingPosture())->toBeFalse()
        ->and($concern->fresh()->inheritsCustomerBilling())->toBeTrue()
        ->and($concern->fresh()->effectiveBillingClassKey())->toBe('warranty_other');
});

test('new concerns inherit the customer class and never stamp the repair order default', function () {
    $customer = Customer::query()->create(['first_name' => 'Synthetic', 'last_name' => 'Military', 'phone' => '555-0164', 'customer_type' => 'Military']);
    $repairOrder = visitBillingRepairOrder($customer);
    $first = visitBillingConcern($repairOrder, 'First');

    expect($first->inheritsCustomerBilling())->toBeTrue()
        ->and($first->billingClassKey())->toBe('default')
        ->and($repairOrder->fresh()->hasOwnBillingPosture())->toBeFalse();

    $customer->update(['customer_type' => 'Fleet']);

    expect(visitBillingConcern($repairOrder->fresh(), 'After class change')->effectiveBillingClassKey())->toBe('fleet')
        ->and($first->fresh()->effectiveBillingClassKey())->toBe('fleet');
});
