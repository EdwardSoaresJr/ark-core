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

test('changing a Warranty visit to RepairPal moves its Warranty concerns and reprices their labor only', function () {
    $customer = Customer::query()->create(['first_name' => 'Synthetic', 'last_name' => 'Warranty', 'phone' => '555-0161', 'customer_type' => 'Warranty']);
    $repairOrder = visitBillingRepairOrder($customer);
    $otherRepairOrder = visitBillingRepairOrder($customer);

    $warrantyA = visitBillingConcern($repairOrder, 'Warranty A');
    $warrantyB = visitBillingConcern($repairOrder, 'Warranty B');
    $customerPay = visitBillingConcern($repairOrder, 'Customer pay C', ConcernBillingPosture::CustomerPay);
    $fleet = visitBillingConcern($repairOrder, 'Fleet D', ConcernBillingPosture::Fleet);
    $laborA = visitBillingLabor($repairOrder, $warrantyA, 'warranty-other');
    $laborB = visitBillingLabor($repairOrder, $warrantyB, 'warranty-other');
    $laborC = visitBillingLabor($repairOrder, $customerPay, 'mechanical');
    $laborD = visitBillingLabor($repairOrder, $fleet, 'mechanical');

    $otherConcern = visitBillingConcern($otherRepairOrder, 'Other visit warranty');
    $otherLabor = visitBillingLabor($otherRepairOrder, $otherConcern, 'warranty-other');
    $otherBefore = [
        'repair_order' => $otherRepairOrder->fresh()->getAttributes(),
        'concern' => $otherConcern->fresh()->getAttributes(),
        'line' => $otherLabor->fresh()->getAttributes(),
    ];
    $unchangedBefore = [$laborC->id => $laborC->fresh()->getAttributes(), $laborD->id => $laborD->fresh()->getAttributes()];

    expect($warrantyA->billing_posture)->toBe(ConcernBillingPosture::WarrantyOther)
        ->and($repairOrder->fresh()->defaultBillingPosture())->toBe(ConcernBillingPosture::WarrantyOther)
        ->and($repairOrder->fresh()->hasOwnBillingPosture())->toBeFalse();

    visitBillingApply($repairOrder, ConcernBillingPosture::RepairPal)
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', 'Visit billing changed from Warranty to RepairPal. 2 concerns on Warranty moved to RepairPal. 2 labor lines repriced. The customer\'s billing class does not change.');

    expect($warrantyA->fresh()->billing_posture)->toBe(ConcernBillingPosture::RepairPal)
        ->and($warrantyB->fresh()->billing_posture)->toBe(ConcernBillingPosture::RepairPal)
        ->and($customerPay->fresh()->billing_posture)->toBe(ConcernBillingPosture::CustomerPay)
        ->and($fleet->fresh()->billing_posture)->toBe(ConcernBillingPosture::Fleet)
        ->and($laborA->fresh())->unit_price_cents->toBe(15000)->labor_category_key->toBe('repairpal')
        ->and($laborB->fresh())->unit_price_cents->toBe(15000)->labor_category_key->toBe('repairpal');

    foreach ($unchangedBefore as $lineId => $attributes) {
        expect(collect(RepairOrderLine::query()->findOrFail($lineId)->getAttributes())->except('updated_at')->all())
            ->toBe(collect($attributes)->except('updated_at')->all());
    }

    expect($customer->fresh()->customer_type)->toBe('Warranty')
        ->and($repairOrder->fresh()->defaultBillingPosture())->toBe(ConcernBillingPosture::RepairPal)
        ->and($otherRepairOrder->fresh()->getAttributes())->toBe($otherBefore['repair_order'])
        ->and($otherConcern->fresh()->getAttributes())->toBe($otherBefore['concern'])
        ->and($otherLabor->fresh()->getAttributes())->toBe($otherBefore['line'])
        ->and($otherRepairOrder->fresh()->defaultBillingPosture())->toBe(ConcernBillingPosture::WarrantyOther);

    $newConcern = visitBillingConcern($repairOrder, 'New after switch E');
    expect($newConcern->billing_posture)->toBe(ConcernBillingPosture::RepairPal);

    $event = OperationalEvent::query()->where('event_name', OperationalEventName::RepairOrderBillingChanged->value)->sole();
    expect($event->payload_json)->toMatchArray([
        'prior_billing_posture' => 'warranty_other',
        'new_billing_posture' => 'repairpal',
        'followed_concern_ids' => [$warrantyA->id, $warrantyB->id],
        'unchanged_concern_ids' => [$customerPay->id, $fleet->id],
        'labor_lines_repriced' => 2,
        'labor_lines_kept' => 0,
    ])->and(OperationalEvent::query()->where('event_name', OperationalEventName::ConcernBillingChanged->value)->pluck('payload_json')->pluck('source')->all())
        ->toBe(['repair_order', 'repair_order']);
});

test('the visit billing preview reports the change without making it', function () {
    $customer = Customer::query()->create(['first_name' => 'Synthetic', 'last_name' => 'Preview', 'phone' => '555-0162', 'customer_type' => 'Warranty']);
    $repairOrder = visitBillingRepairOrder($customer);
    $warranty = visitBillingConcern($repairOrder, 'Warranty brakes');
    visitBillingConcern($repairOrder, 'Customer pay oil', ConcernBillingPosture::CustomerPay);
    $labor = visitBillingLabor($repairOrder, $warranty, 'warranty-other');
    $version = (int) $repairOrder->fresh()->estimate_version;

    $this->getJson(route('operations.repair-orders.billing-posture.preview', [$repairOrder, 'billing_posture' => 'repairpal']))
        ->assertOk()
        ->assertJson([
            'prior' => 'warranty_other',
            'next' => 'repairpal',
            'concerns_following' => 1,
            'concerns_unchanged' => 1,
            'labor_lines_repriced' => 1,
            'labor_lines_kept' => 0,
            'summary' => 'Visit billing changes from Warranty to RepairPal. 1 concern on Warranty moves to RepairPal. 1 labor line reprices. The customer\'s billing class does not change.',
            'details' => [
                'Warranty brakes: '.$labor->description.' $165.00 → $150.00/hr',
                'Customer pay oil: stays Customer pay',
            ],
        ]);

    expect($warranty->fresh()->billing_posture)->toBe(ConcernBillingPosture::WarrantyOther)
        ->and($labor->fresh()->unit_price_cents)->toBe(16500)
        ->and($repairOrder->fresh()->hasOwnBillingPosture())->toBeFalse()
        ->and((int) $repairOrder->fresh()->estimate_version)->toBe($version);

    $this->getJson(route('operations.repair-orders.billing-posture.preview', [$repairOrder, 'billing_posture' => 'warranty_repairpal']))
        ->assertUnprocessable();
});

test('a visit keeps approved labor at its approved rate when its billing changes', function () {
    $customer = Customer::query()->create(['first_name' => 'Synthetic', 'last_name' => 'Approved', 'phone' => '555-0163', 'customer_type' => 'Warranty']);
    $repairOrder = visitBillingRepairOrder($customer);
    $approved = visitBillingConcern($repairOrder, 'Approved warranty');
    $open = visitBillingConcern($repairOrder, 'Open warranty');
    $approvedLabor = visitBillingLabor($repairOrder, $approved, 'warranty-other');
    $openLabor = visitBillingLabor($repairOrder, $open, 'warranty-other');
    $approved->update(['disposition' => RepairOrderConcernDisposition::Approved]);
    approveCurrentAmounts($repairOrder);

    visitBillingApply($repairOrder, ConcernBillingPosture::RepairPal)
        ->assertSessionHas('status', 'Visit billing changed from Warranty to RepairPal. 2 concerns on Warranty moved to RepairPal. 1 labor line repriced. 1 labor line keeps its rate (approved, custom or special pricing). The customer\'s billing class does not change.');

    expect($approved->fresh()->billing_posture)->toBe(ConcernBillingPosture::RepairPal)
        ->and($approvedLabor->fresh())->unit_price_cents->toBe(16500)->labor_category_key->toBe('warranty-other')
        ->and($openLabor->fresh()->unit_price_cents)->toBe(15000);
});

test('a visit without its own billing follows the customer until staff set it', function () {
    $customer = Customer::query()->create(['first_name' => 'Synthetic', 'last_name' => 'Military', 'phone' => '555-0164', 'customer_type' => 'Military']);
    $repairOrder = visitBillingRepairOrder($customer);

    expect($repairOrder->defaultBillingPosture())->toBe(ConcernBillingPosture::Default)
        ->and(visitBillingConcern($repairOrder, 'First')->billing_posture)->toBe(ConcernBillingPosture::Default);

    $customer->update(['customer_type' => 'Fleet']);
    expect(visitBillingConcern($repairOrder->fresh(), 'After class change')->billing_posture)->toBe(ConcernBillingPosture::Fleet);

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('data-visit-billing="fleet"', false)
        ->assertSee('data-workspace-modal-form="visit-billing"', false);
});

test('a closed repair order keeps its billing', function () {
    $customer = Customer::query()->create(['first_name' => 'Synthetic', 'last_name' => 'Closed', 'phone' => '555-0165', 'customer_type' => 'Warranty']);
    $repairOrder = visitBillingRepairOrder($customer);
    visitBillingConcern($repairOrder, 'Warranty');
    $repairOrder->forceFill(['status' => RepairOrderStatus::Closed])->save();

    visitBillingApply($repairOrder, ConcernBillingPosture::RepairPal)->assertStatus(423);

    expect($repairOrder->fresh()->hasOwnBillingPosture())->toBeFalse()
        ->and($repairOrder->concerns()->sole()->billing_posture)->toBe(ConcernBillingPosture::WarrantyOther);
});
