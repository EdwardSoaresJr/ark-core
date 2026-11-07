<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\EstimatePricing\LaborRateOverrideReason;
use App\Ark\Operations\Events\OperationalEvent;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Events\OperationalEventRecorder;
use App\Ark\Operations\Financial\BalanceDueCalculator;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Financial\PaymentMethod;
use App\Ark\Operations\Financial\RecordLedgerEntryAction;
use App\Ark\Operations\RepairOrders\ApprovedWorkScope;
use App\Ark\Operations\RepairOrders\ChangeConcernBillingPostureAction;
use App\Ark\Operations\RepairOrders\ConcernBillingPosture;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\RepairOrders\WorkCompletionAuthorization;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    ShopSettings::current()->update([
        'labor_categories' => ShopSettings::DEFAULT_LABOR_CATEGORIES,
        'default_labor_rate_cents' => 16500,
        'customer_types' => ShopSettings::DEFAULT_CUSTOMER_TYPES,
        'tax_enabled' => true,
        'default_tax_rate' => '8.000',
        'taxable_labor' => true,
        'taxable_parts' => true,
        'taxable_shop_fees' => false,
        'shop_fee_enabled' => true,
        'shop_fee_rate' => '5.000',
        'shop_fee_cap_cents' => null,
    ]);
    $this->advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $this->actingAs($this->advisor);
});

function billingRepricingRepairOrder(string $customerType = 'Warranty'): RepairOrder
{
    $customer = Customer::query()->create(['first_name' => 'Synthetic', 'last_name' => $customerType, 'phone' => '555-0151', 'customer_type' => $customerType]);
    $vehicle = Vehicle::query()->create(['customer_id' => $customer->id, 'year' => 2020, 'make' => 'Toyota', 'model' => 'Tacoma']);

    return RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Synthetic billing change',
    ]);
}

function billingRepricingConcern(RepairOrder $repairOrder, string $summary, ConcernBillingPosture $posture): RepairOrderConcern
{
    return RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => $summary,
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'position' => $repairOrder->concerns()->count() + 1,
        'billing_posture' => $posture,
    ]);
}

/**
 * @param  array<string, mixed>  $overrides
 */
function billingRepricingLabor(RepairOrder $repairOrder, RepairOrderConcern $concern, string $category, array $overrides = []): RepairOrderLine
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
        ...$overrides,
    ])->assertSessionHasNoErrors();

    return RepairOrderLine::query()->where('description', $description)->sole();
}

function billingRepricingChange(RepairOrder $repairOrder, RepairOrderConcern $concern, ConcernBillingPosture $posture): TestResponse
{
    return test()->patch(route('operations.repair-orders.concerns.billing-posture', [$repairOrder, $concern]), [
        'billing_posture' => $posture->value,
    ]);
}

function billingRepricingEvent(): OperationalEvent
{
    return OperationalEvent::query()
        ->where('event_name', OperationalEventName::ConcernBillingChanged->value)
        ->latest('id')
        ->firstOrFail();
}

test('Molly: Warranty labor at $165 reprices to RepairPal $150 when the concern moves to RepairPal', function () {
    $repairOrder = billingRepricingRepairOrder();
    $warranty = billingRepricingConcern($repairOrder, 'Warranty brakes', ConcernBillingPosture::WarrantyOther);
    $line = billingRepricingLabor($repairOrder, $warranty, 'warranty-other');

    expect($line)->unit_price_cents->toBe(16500)->quantity->toBe('2.00')->total_cents->toBe(35640);

    test()->patch(route('operations.repair-orders.lines.update', [$repairOrder, $line]), [
        'repair_order_concern_id' => $warranty->id,
        'type' => 'labor',
        'description' => $line->description,
        'labor_category_key' => 'warranty-other',
        'labor_entered_hours' => '2.00',
        'labor_adjustment' => 'normal',
        'unit_price' => '150.00',
        'labor_rate_overridden' => '1',
        'labor_rate_override_reason' => LaborRateOverrideReason::CompetitiveMatch->value,
    ])->assertSessionHasErrors('unit_price');
    expect($line->fresh()->unit_price_cents)->toBe(16500);

    billingRepricingChange($repairOrder, $warranty, ConcernBillingPosture::RepairPal)
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', 'Billing updated for Warranty brakes. 1 labor line repriced at the RepairPal rate.');

    $line = $line->fresh();
    expect($line)
        ->labor_category_key->toBe('repairpal')
        ->unit_price_cents->toBe(15000)
        ->labor_rate_cents->toBe(15000)
        ->policy_resolved_labor_rate_cents->toBe(15000)
        ->resolved_from_posture->toBe('repairpal')
        ->quantity->toBe('2.00')
        ->subtotal_cents->toBe(30000)
        ->shop_fee_cents->toBe(0)
        ->tax_cents->toBe(2400)
        ->total_cents->toBe(32400);

    expect(billingRepricingEvent()->payload_json)->toMatchArray([
        'concern_id' => $warranty->id,
        'source' => 'concern',
        'prior_billing_posture' => 'warranty_other',
        'new_billing_posture' => 'repairpal',
        'repriced' => [[
            'line_id' => $line->id,
            'prior_rate_cents' => 16500,
            'new_rate_cents' => 15000,
            'prior_category' => 'warranty-other',
            'new_category' => 'repairpal',
        ]],
        'kept' => [],
    ]);

    $this->get(route('operations.repair-orders.show', ['repairOrder' => $repairOrder, 'editing_line' => $line->id]))
        ->assertOk()
        ->assertSee("sell: '150.00'", false);

    test()->patch(route('operations.repair-orders.lines.update', [$repairOrder, $line]), [
        'repair_order_concern_id' => $warranty->id,
        'type' => 'labor',
        'description' => 'Brake pads and rotors',
        'labor_category_key' => 'repairpal',
        'labor_entered_hours' => '2.00',
        'labor_adjustment' => 'normal',
        'unit_price' => '150.00',
        'labor_rate_overridden' => '0',
    ])->assertSessionHasNoErrors();

    expect($line->fresh())->unit_price_cents->toBe(15000)->total_cents->toBe(32400);
});

test('a billing change reprices only the changed concern on a mixed repair order', function () {
    $repairOrder = billingRepricingRepairOrder();
    $warranty = billingRepricingConcern($repairOrder, 'Warranty', ConcernBillingPosture::WarrantyOther);
    $customerPay = billingRepricingConcern($repairOrder, 'Customer pay', ConcernBillingPosture::CustomerPay);
    $warrantyLine = billingRepricingLabor($repairOrder, $warranty, 'warranty-other');
    $customerPayLine = billingRepricingLabor($repairOrder, $customerPay, 'mechanical');
    $customerPayBefore = $customerPayLine->fresh()->getAttributes();

    billingRepricingChange($repairOrder, $warranty, ConcernBillingPosture::RepairPal)->assertSessionHasNoErrors();

    expect($warrantyLine->fresh()->unit_price_cents)->toBe(15000)
        ->and($customerPay->fresh()->billing_posture)->toBe(ConcernBillingPosture::CustomerPay)
        ->and(collect($customerPayLine->fresh()->getAttributes())->except(['updated_at'])->all())
        ->toBe(collect($customerPayBefore)->except(['updated_at'])->all());
});

test('a billing change keeps custom, menu, special and unknown pricing and says why', function () {
    $repairOrder = billingRepricingRepairOrder();
    $warranty = billingRepricingConcern($repairOrder, 'Warranty', ConcernBillingPosture::WarrantyOther);
    $custom = billingRepricingLabor($repairOrder, $warranty, 'mechanical', [
        'unit_price' => '140.00',
        'labor_rate_overridden' => '1',
        'labor_rate_override_reason' => LaborRateOverrideReason::ManagementApproval->value,
    ]);
    $courtesy = billingRepricingLabor($repairOrder, $warranty, 'courtesy');
    $legacy = billingRepricingLabor($repairOrder, $warranty, 'mechanical');
    $legacy->forceFill(['labor_policy_id' => null, 'policy_resolved_labor_rate_cents' => null])->save();
    $mismatched = billingRepricingLabor($repairOrder, $warranty, 'mechanical');
    $mismatched->forceFill(['unit_price_cents' => 15500, 'subtotal_cents' => 31000])->save();
    $policyPriced = billingRepricingLabor($repairOrder, $warranty, 'mechanical');

    $before = collect([$custom, $courtesy, $legacy, $mismatched])->mapWithKeys(fn (RepairOrderLine $line): array => [
        $line->id => $line->fresh()->unit_price_cents,
    ]);

    billingRepricingChange($repairOrder, $warranty, ConcernBillingPosture::RepairPal)
        ->assertSessionHas('status', 'Billing updated for Warranty. 1 labor line repriced at the RepairPal rate. 4 labor lines kept their rate (approved, custom or special pricing).');

    foreach ($before as $lineId => $rate) {
        expect(RepairOrderLine::query()->findOrFail($lineId)->unit_price_cents)->toBe($rate);
    }

    expect($custom->fresh()->labor_rate_override_reason)->toBe('management_approval')
        ->and($policyPriced->fresh())
        ->labor_category_key->toBe('mechanical')
        ->unit_price_cents->toBe(15000)
        ->resolved_from_posture->toBe('repairpal')
        ->and(collect(billingRepricingEvent()->payload_json['kept'])->pluck('reason', 'line_id')->all())->toBe([
            $custom->id => ChangeConcernBillingPostureAction::SKIP_MANUAL_RATE,
            $courtesy->id => ChangeConcernBillingPostureAction::SKIP_SPECIAL_PRICING,
            $legacy->id => ChangeConcernBillingPostureAction::SKIP_UNKNOWN_PRICING,
            $mismatched->id => ChangeConcernBillingPostureAction::SKIP_UNKNOWN_PRICING,
        ]);
});

test('approved labor keeps its rate while fees follow the new billing and the increase waits for reauthorization', function () {
    $repairOrder = billingRepricingRepairOrder();
    $warranty = billingRepricingConcern($repairOrder, 'Warranty', ConcernBillingPosture::WarrantyOther);
    $line = billingRepricingLabor($repairOrder, $warranty, 'warranty-other');
    $warranty->update(['disposition' => RepairOrderConcernDisposition::Approved]);
    approveCurrentAmounts($repairOrder);
    $calculator = app(EstimateTotalsCalculator::class);
    $approvedBefore = $calculator->approvedTotalsForRead($repairOrder->fresh(['lines.concern']))->totalCents();

    billingRepricingChange($repairOrder, $warranty, ConcernBillingPosture::CustomerPay)->assertSessionHasNoErrors();

    $repairOrder = $repairOrder->fresh(['concerns', 'lines.concern']);
    $authorization = app(WorkCompletionAuthorization::class);

    expect($line->fresh())
        ->unit_price_cents->toBe(16500)
        ->labor_category_key->toBe('warranty-other')
        ->shop_fee_cents->toBe(1650)
        ->and(collect(billingRepricingEvent()->payload_json['kept'])->pluck('reason')->all())->toBe([ChangeConcernBillingPostureAction::SKIP_APPROVED])
        ->and($approvedBefore)->toBe(35640)
        ->and($calculator->approvedTotalsForRead($repairOrder)->totalCents())->toBe(35640)
        ->and($authorization->linesRequiringAuthorization($repairOrder)->pluck('id')->all())->toBe([$line->id])
        ->and($authorization->workOutsideApprovedSalesCents($repairOrder))->toBe(1650);
});

test('a Military RepairPal concern moved to Customer Pay picks up the labor discount, the shop fee and the Customer Pay rate', function () {
    $repairOrder = billingRepricingRepairOrder('Military');
    $repairPal = billingRepricingConcern($repairOrder, 'RepairPal', ConcernBillingPosture::RepairPal);
    $line = billingRepricingLabor($repairOrder, $repairPal, 'repairpal');

    expect($line->fresh())
        ->unit_price_cents->toBe(15000)
        ->standing_discount_cents->toBe(0)
        ->shop_fee_cents->toBe(0)
        ->total_cents->toBe(32400);

    billingRepricingChange($repairOrder, $repairPal, ConcernBillingPosture::CustomerPay)->assertSessionHasNoErrors();

    expect($line->fresh())
        ->labor_category_key->toBe('mechanical')
        ->unit_price_cents->toBe(16500)
        ->quantity->toBe('2.00')
        ->subtotal_cents->toBe(33000)
        ->standing_discount_cents->toBe(3300)
        ->shop_fee_cents->toBe(1485)
        ->tax_cents->toBe(2376)
        ->total_cents->toBe(33561);
});

test('a billing change and its repricing roll back together', function () {
    $repairOrder = billingRepricingRepairOrder();
    $warranty = billingRepricingConcern($repairOrder, 'Warranty', ConcernBillingPosture::WarrantyOther);
    $line = billingRepricingLabor($repairOrder, $warranty, 'warranty-other');

    $this->mock(OperationalEventRecorder::class)
        ->shouldReceive('record')
        ->andThrow(new RuntimeException('Event store unavailable.'));

    expect(fn () => app(ChangeConcernBillingPostureAction::class)->execute(
        $repairOrder,
        $warranty,
        ConcernBillingPosture::RepairPal,
        $this->advisor,
    ))->toThrow(RuntimeException::class, 'Event store unavailable.');

    expect($warranty->fresh()->billing_posture)->toBe(ConcernBillingPosture::WarrantyOther)
        ->and($line->fresh())
        ->unit_price_cents->toBe(16500)
        ->labor_category_key->toBe('warranty-other')
        ->total_cents->toBe(35640);
});

test('a billing change updates a presented, partly paid invoice and keeps the payment', function () {
    $this->seed(RepairOrderStatusCatalogSeeder::class);
    $repairOrder = billingRepricingRepairOrder('Retail');
    $concern = billingRepricingConcern($repairOrder, 'Customer pay', ConcernBillingPosture::CustomerPay);
    $line = billingRepricingLabor($repairOrder, $concern, 'mechanical');
    $concern->update(['disposition' => RepairOrderConcernDisposition::Approved]);
    approveCurrentAmounts($repairOrder);
    issueFinalInvoiceFor($repairOrder->fresh());
    $balances = app(BalanceDueCalculator::class);
    $balances->issuedInvoice($repairOrder->fresh())->markPresentedToCustomer();
    app(RecordLedgerEntryAction::class)->recordPayment($repairOrder->fresh(), 10000, PaymentMethod::Card);

    expect($balances->forRepairOrder($repairOrder->fresh())->invoiceTotalCents)->toBe(37290);

    billingRepricingChange($repairOrder, $concern, ConcernBillingPosture::WarrantyOther)->assertSessionHasNoErrors();

    $balance = $balances->forRepairOrder($repairOrder->fresh());

    expect($line->fresh())->unit_price_cents->toBe(16500)->shop_fee_cents->toBe(0)
        ->and($balance->invoiceTotalCents)->toBe(35640)
        ->and($balance->paymentsAppliedCents)->toBe(10000)
        ->and($balance->balanceDueCents)->toBe(25640)
        ->and($balances->issuedInvoice($repairOrder->fresh())->snapshot_revisions_json)->toHaveCount(1);
});

test('staff can deliberately reduce approved labor kept at the old rate, keeping its approval and updating the invoice', function () {
    $this->seed(RepairOrderStatusCatalogSeeder::class);
    $repairOrder = billingRepricingRepairOrder();
    $concern = billingRepricingConcern($repairOrder, 'Warranty', ConcernBillingPosture::WarrantyOther);
    $line = billingRepricingLabor($repairOrder, $concern, 'warranty-other');
    $concern->update(['disposition' => RepairOrderConcernDisposition::Approved]);
    approveCurrentAmounts($repairOrder);
    issueFinalInvoiceFor($repairOrder->fresh());
    $approvalScopes = ApprovedWorkScope::query()->where('repair_order_concern_id', $concern->id)->get()->map->getAttributes()->all();

    billingRepricingChange($repairOrder, $concern, ConcernBillingPosture::RepairPal)->assertSessionHasNoErrors();
    expect($line->fresh()->unit_price_cents)->toBe(16500);

    $this->patch(route('operations.repair-orders.lines.update', [$repairOrder, $line]), [
        'repair_order_concern_id' => $concern->id,
        'type' => 'labor',
        'description' => $line->description,
        'labor_category_key' => 'warranty-other',
        'labor_entered_hours' => '2.00',
        'labor_adjustment' => 'normal',
        'unit_price' => '150.00',
        'labor_rate_overridden' => '0',
        'reprice_labor' => '1',
    ])->assertSessionHasNoErrors();

    $repairOrder = $repairOrder->fresh(['concerns', 'lines.concern']);

    expect($line->fresh())->unit_price_cents->toBe(15000)->resolved_from_posture->toBe('repairpal')
        ->and($concern->fresh()->disposition)->toBe(RepairOrderConcernDisposition::Approved)
        ->and(ApprovedWorkScope::query()->where('repair_order_concern_id', $concern->id)->get()->map->getAttributes()->all())->toBe($approvalScopes)
        ->and(app(WorkCompletionAuthorization::class)->linesRequiringAuthorization($repairOrder))->toHaveCount(0)
        ->and(app(EstimateTotalsCalculator::class)->approvedTotalsForRead($repairOrder)->totalCents())->toBe(32400)
        ->and(app(BalanceDueCalculator::class)->forRepairOrder($repairOrder)->invoiceTotalCents)->toBe(32400);
});

test('a zero-rate policy and an explicit no-charge rate stay $0, while Courtesy on Customer Pay keeps its policy rate', function () {
    $repairOrder = billingRepricingRepairOrder('Retail');
    $comeback = billingRepricingConcern($repairOrder, 'Comeback', ConcernBillingPosture::Comeback);
    $customerPay = billingRepricingConcern($repairOrder, 'Customer pay', ConcernBillingPosture::CustomerPay);

    $zeroRatePolicy = billingRepricingLabor($repairOrder, $comeback, 'mechanical');
    $noCharge = billingRepricingLabor($repairOrder, $customerPay, 'mechanical', [
        'unit_price' => '0.00',
        'labor_rate_overridden' => '1',
        'labor_rate_override_reason' => LaborRateOverrideReason::CustomerGoodwill->value,
    ]);
    $courtesy = billingRepricingLabor($repairOrder, $customerPay, 'courtesy');

    expect($zeroRatePolicy->fresh())->unit_price_cents->toBe(0)->resolved_from_posture->toBe('comeback')
        ->and($noCharge->fresh())->unit_price_cents->toBe(0)->labor_rate_override_reason->toBe('customer_goodwill')
        ->and($courtesy->fresh())->unit_price_cents->toBe(16500)->resolved_from_posture->toBe('customer_pay');

    billingRepricingChange($repairOrder, $comeback, ConcernBillingPosture::CustomerPay)->assertSessionHasNoErrors();
    billingRepricingChange($repairOrder, $customerPay, ConcernBillingPosture::RepairPal)->assertSessionHasNoErrors();

    expect($zeroRatePolicy->fresh()->unit_price_cents)->toBe(0)
        ->and($noCharge->fresh()->unit_price_cents)->toBe(0)
        ->and($noCharge->fresh()->labor_rate_override_reason)->toBe('customer_goodwill');
});
