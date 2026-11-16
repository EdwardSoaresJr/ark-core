<?php

/**
 * F-FIN-01 — Authorized-cent drift after unapproved estimate increase.
 *
 * Owner case (campaign overnight-financial-comms-integrity-2026-11):
 *   Estimate $1,200.00; approve concern A at $900.00; raise A labor without supplemental.
 *   Independent expected authorized cents stay 90,000. Vulnerable code drifted to 89,895.
 *
 * Expected cents are independent of EstimateTotalsCalculator's approved read path.
 */

use App\Ark\Operations\Approvals\ApprovalEvent;
use App\Ark\Operations\Approvals\ApprovalSource;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Financial\GenerateInvoiceSnapshotAction;
use App\Ark\Operations\Financial\InvoiceSnapshotBuilder;
use App\Ark\Operations\RepairOrders\ConcernBillingPosture;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderAuthorizationStoreController;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderConcurrency;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\RepairOrders\WorkCompletionAuthorization;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
    $this->admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
    $this->actingAs($this->admin);
});

test('F-FIN-01: capped percent shop fee re-proration cannot shrink a $900.00 authorization', function () {
    driftConfigureOwnerShop(capCents: 4000);
    $built = driftBuildOwnerEstimate();
    $ro = $built['ro'];
    $calculator = app(EstimateTotalsCalculator::class);

    expect((int) RepairOrderLine::query()->where('repair_order_id', $ro->id)->sum('total_cents'))->toBe(120000)
        ->and(driftConcernTotal($ro, $built['A']))->toBe(90000)
        ->and(driftConcernTotal($ro, $built['B']))->toBe(30000);

    driftDecline($ro, $built['B']);
    driftApprove($ro, '900.00');

    $authorizedAtApproval = $calculator->approvedTotalsForRead($ro->fresh(['lines.concern', 'concerns']))->totalCents();
    expect($authorizedAtApproval)->toBe(90000)
        ->and((int) ApprovalEvent::query()->where('visit_id', $ro->id)->latest('id')->value('approved_amount_cents'))->toBe(90000);

    // Unapproved increase: A labor 2.50h → 3.00h. Living A becomes 98,981; authorization must stay 90,000.
    driftRaiseLabor($ro, $built['lines']['A-labor'], '3.00');
    $ro = $ro->fresh(['lines.concern', 'concerns', 'customer']);

    $authorizedAfter = $calculator->approvedTotalsForRead($ro)->totalCents();
    $invoiceBasis = $calculator->totalsForApprovedWork($ro)->totalCents();
    $livingA = driftConcernTotal($ro, $built['A']);
    $outside = app(WorkCompletionAuthorization::class)->workOutsideApprovedSalesCents($ro);

    expect($livingA)->toBe(98981)
        ->and($authorizedAfter)->toBe(90000)
        ->and($invoiceBasis)->toBe(90000)
        ->and($outside)->toBe(8981)
        ->and($authorizedAfter + $outside)->toBe($livingA)
        ->and(ApprovalEvent::query()->where('visit_id', $ro->id)->count())->toBe(1);

    // Second raise 3.50h — still no new approval event; authorized cents stay $900.00.
    driftRaiseLabor($ro, $built['lines']['A-labor']->fresh(), '3.50');
    $ro = $ro->fresh(['lines.concern', 'concerns', 'customer']);
    expect($calculator->approvedTotalsForRead($ro)->totalCents())->toBe(90000)
        ->and($calculator->totalsForApprovedWork($ro)->totalCents())->toBe(90000);
});

test('F-FIN-01 control: without a fee cap authorized cents stay exact after the same raise', function () {
    driftConfigureOwnerShop(capCents: null);
    $built = driftBuildOwnerEstimate();
    $ro = $built['ro'];
    $calculator = app(EstimateTotalsCalculator::class);

    $aAtBuild = driftConcernTotal($ro, $built['A']);
    driftDecline($ro, $built['B']);
    driftApprove($ro, number_format($aAtBuild / 100, 2, '.', ''));
    $authorized = $calculator->approvedTotalsForRead($ro->fresh(['lines.concern']))->totalCents();
    expect($authorized)->toBe($aAtBuild);

    driftRaiseLabor($ro, $built['lines']['A-labor'], '3.00');
    expect($calculator->approvedTotalsForRead($ro->fresh(['lines.concern']))->totalCents())->toBe($authorized);
});

test('intentional price decrease after approval may bill below the prior authorization', function () {
    // Single-line concern (no sibling fee reallocation): a real price cut lowers authorized cents.
    ShopSettings::current()->update([
        'tax_enabled' => false,
        'shop_fee_enabled' => false,
        'portal_signature_required' => false,
    ]);
    $customer = Customer::query()->create(['first_name' => 'Cut', 'last_name' => 'Price', 'phone' => '555-0177', 'customer_type' => 'Retail']);
    $vehicle = Vehicle::query()->create(['customer_id' => $customer->id, 'year' => 2016, 'make' => 'Ford', 'model' => 'Focus']);
    $ro = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::WaitingApproval,
        'concern_summary' => 'Price cut',
    ]);
    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $ro->id,
        'summary' => 'Pads',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'billing_posture' => ConcernBillingPosture::CustomerPay,
        'position' => 1,
    ]);
    $part = RepairOrderLine::query()->create([
        'repair_order_id' => $ro->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Part,
        'description' => 'Pads',
        'quantity' => '1.00',
        'unit_price_cents' => 20000,
        'part_cost_cents' => 8000,
        'subtotal_cents' => 20000,
        'total_cents' => 20000,
    ]);
    app(EstimateTotalsCalculator::class)->recalculateRepairOrder($ro->fresh(['lines', 'customer', 'concerns']));
    driftApprove($ro, null);
    expect(app(EstimateTotalsCalculator::class)->approvedTotalsForRead($ro->fresh(['lines.concern']))->totalCents())->toBe(20000);

    $part->fresh()->update(['unit_price_cents' => 10000]);
    app(EstimateTotalsCalculator::class)->recalculateRepairOrder($ro->fresh(['lines', 'customer', 'concerns']));

    $approved = app(EstimateTotalsCalculator::class)->approvedTotalsForRead($ro->fresh(['lines.concern']))->totalCents();
    $living = app(EstimateTotalsCalculator::class)->totalsFor($ro->fresh(['lines.concern']))->totalCents();

    expect($approved)->toBe(10000)
        ->and($approved)->toBe($living)
        ->and(app(WorkCompletionAuthorization::class)->linesRequiringAuthorization($ro->fresh(['lines.concern'])))->toBeEmpty();
});

test('flat shop fee does not drift authorized cents after an unapproved labor raise', function () {
    ShopSettings::current()->update([
        'labor_categories' => ShopSettings::DEFAULT_LABOR_CATEGORIES,
        'default_labor_rate_cents' => 16500,
        'tax_enabled' => false,
        'shop_fee_enabled' => true,
        'shop_fee_mode' => 'flat',
        'shop_fee_flat_cents' => 2500,
        'shop_fee_cap_cents' => null,
        'portal_signature_required' => false,
        'customer_types' => collect([
            ['name' => 'Retail', 'discount_type' => 'none', 'default_parts_matrix_key' => 'aft-parts'],
        ])->map(fn (array $row): array => ShopSettings::current()->normalizeStoredCustomerTypeRow($row))->all(),
    ]);

    $customer = Customer::query()->create(['first_name' => 'Flat', 'last_name' => 'Fee', 'phone' => '555-0199', 'customer_type' => 'Retail']);
    $vehicle = Vehicle::query()->create(['customer_id' => $customer->id, 'year' => 2019, 'make' => 'Honda', 'model' => 'Civic']);
    $ro = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::WaitingApproval,
        'concern_summary' => 'Flat fee drift',
    ]);
    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $ro->id,
        'summary' => 'Work',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'billing_posture' => ConcernBillingPosture::CustomerPay,
        'position' => 1,
    ]);
    $labor = driftStoreLabor($ro, $concern, 'Labor', '1.00');
    app(EstimateTotalsCalculator::class)->recalculateRepairOrder($ro->fresh(['lines', 'customer', 'concerns']));
    driftApprove($ro, null);
    $authorized = app(EstimateTotalsCalculator::class)->approvedTotalsForRead($ro->fresh(['lines.concern']))->totalCents();

    driftRaiseLabor($ro, $labor, '2.00');
    expect(app(EstimateTotalsCalculator::class)->approvedTotalsForRead($ro->fresh(['lines.concern']))->totalCents())->toBe($authorized);
});

test('invoice while increase is unapproved stays at the exact authorization under a binding fee cap', function () {
    driftConfigureOwnerShop(capCents: 4000);
    $built = driftBuildOwnerEstimate();
    $ro = $built['ro'];
    driftDecline($ro, $built['B']);
    driftApprove($ro, '900.00');
    driftRaiseLabor($ro, $built['lines']['A-labor'], '3.00');

    $ro->forceFill(['status' => RepairOrderStatus::ReadyPickup])->save();
    $invoice = app(GenerateInvoiceSnapshotAction::class)->execute($ro->fresh(['lines.concern', 'concerns', 'customer']));

    expect(InvoiceSnapshotBuilder::invoiceTotalCents($invoice->snapshot_json ?? []))->toBe(90000);
});

test('quantity increase after approval holds the authorized ceiling until supplemental approval', function () {
    [$ro, $part, $authorized] = driftSinglePartShop(feeCapCents: 4000, unitPriceCents: 50000, qty: '1.00');
    $calculator = app(EstimateTotalsCalculator::class);

    $part->fresh()->update(['quantity' => '2.00']);
    $calculator->recalculateRepairOrder($ro->fresh(['lines', 'customer', 'concerns']));
    $ro = $ro->fresh(['lines.concern', 'concerns']);

    expect($calculator->approvedTotalsForRead($ro)->totalCents())->toBe($authorized)
        ->and((int) $part->fresh()->total_cents)->toBeGreaterThan($authorized);

    driftApprove($ro, null);
    expect($calculator->approvedTotalsForRead($ro->fresh(['lines.concern']))->totalCents())
        ->toBe((int) $part->fresh()->total_cents);
});

test('unit price increase after approval holds the authorized ceiling', function () {
    [$ro, $part, $authorized] = driftSinglePartShop(feeCapCents: 4000, unitPriceCents: 40000, qty: '1.00');
    $calculator = app(EstimateTotalsCalculator::class);

    $part->fresh()->update(['unit_price_cents' => 55000]);
    $calculator->recalculateRepairOrder($ro->fresh(['lines', 'customer', 'concerns']));

    expect($calculator->approvedTotalsForRead($ro->fresh(['lines.concern']))->totalCents())->toBe($authorized);
});

test('no shop fee: unapproved raise keeps exact authorized cents', function () {
    ShopSettings::current()->update([
        'tax_enabled' => true,
        'tax_label' => 'Sales Tax',
        'default_tax_rate' => '8.000',
        'taxable_labor' => true,
        'taxable_parts' => true,
        'taxable_shop_fees' => false,
        'shop_fee_enabled' => false,
        'portal_signature_required' => false,
    ]);
    [$ro, $part, $authorized] = driftSinglePartShop(feeCapCents: null, unitPriceCents: 25000, qty: '1.00', configureShop: false);

    $part->fresh()->update(['unit_price_cents' => 30000]);
    app(EstimateTotalsCalculator::class)->recalculateRepairOrder($ro->fresh(['lines', 'customer', 'concerns']));

    expect(app(EstimateTotalsCalculator::class)->approvedTotalsForRead($ro->fresh(['lines.concern']))->totalCents())->toBe($authorized);
});

test('mixed taxable parts and nontaxable labor: fee-cap raise does not drift authorized cents', function () {
    ShopSettings::current()->update([
        'labor_categories' => ShopSettings::DEFAULT_LABOR_CATEGORIES,
        'default_labor_rate_cents' => 16500,
        'tax_enabled' => true,
        'tax_label' => 'Sales Tax',
        'default_tax_rate' => '8.000',
        'taxable_labor' => false,
        'taxable_parts' => true,
        'taxable_shop_fees' => false,
        'shop_fee_enabled' => true,
        'shop_fee_mode' => 'percent',
        'shop_fee_rate' => '5.000',
        'shop_fee_cap_cents' => 4000,
        'portal_signature_required' => false,
        'parts_matrices' => ShopSettings::DEFAULT_PARTS_MATRICES,
        'customer_types' => collect([
            ['name' => 'Retail', 'discount_type' => 'none', 'default_parts_matrix_key' => 'aft-parts'],
        ])->map(fn (array $row): array => ShopSettings::current()->normalizeStoredCustomerTypeRow($row))->all(),
    ]);

    $customer = Customer::query()->create(['first_name' => 'Mix', 'last_name' => 'Tax', 'phone' => '555-0166', 'customer_type' => 'Retail']);
    $vehicle = Vehicle::query()->create(['customer_id' => $customer->id, 'year' => 2020, 'make' => 'Mazda', 'model' => '3']);
    $ro = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::WaitingApproval,
        'concern_summary' => 'Mixed tax',
    ]);
    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $ro->id,
        'summary' => 'Brakes',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'billing_posture' => ConcernBillingPosture::CustomerPay,
        'position' => 1,
    ]);
    $part = driftStorePart($ro, $concern, 'Pads taxable', 30000);
    $labor = driftStoreLabor($ro, $concern, 'Labor nontaxable', '1.00');
    app(EstimateTotalsCalculator::class)->recalculateRepairOrder($ro->fresh(['lines', 'customer', 'concerns']));
    driftApprove($ro, null);
    $authorized = app(EstimateTotalsCalculator::class)->approvedTotalsForRead($ro->fresh(['lines.concern']))->totalCents();

    driftRaiseLabor($ro, $labor, '2.00');
    expect(app(EstimateTotalsCalculator::class)->approvedTotalsForRead($ro->fresh(['lines.concern']))->totalCents())->toBe($authorized)
        ->and((int) $labor->fresh()->tax_cents)->toBe(0)
        ->and((int) $part->fresh()->tax_cents)->toBeGreaterThan(0);
});

test('percentage parts discount: authorized cents stay after unapproved raise under fee cap', function () {
    driftConfigureOwnerShop(capCents: 4000);
    $built = driftBuildOwnerEstimate();
    $ro = $built['ro'];
    expect($ro->customer->customer_type)->toBe('PartsDiscount10');

    driftDecline($ro, $built['B']);
    driftApprove($ro, '900.00');
    $authorized = app(EstimateTotalsCalculator::class)->approvedTotalsForRead($ro->fresh(['lines.concern']))->totalCents();
    expect($authorized)->toBe(90000);

    $built['lines']['A-part']->fresh()->update(['unit_price_cents' => 50000]);
    app(EstimateTotalsCalculator::class)->recalculateRepairOrder($ro->fresh(['lines', 'customer', 'concerns']));

    expect(app(EstimateTotalsCalculator::class)->approvedTotalsForRead($ro->fresh(['lines.concern']))->totalCents())->toBe(90000);
});

test('supplemental approval after raise covers the new living authorized cents', function () {
    driftConfigureOwnerShop(capCents: 4000);
    $built = driftBuildOwnerEstimate();
    $ro = $built['ro'];
    driftDecline($ro, $built['B']);
    driftApprove($ro, '900.00');
    driftRaiseLabor($ro, $built['lines']['A-labor'], '3.00');
    $ro = $ro->fresh(['lines.concern', 'concerns', 'customer']);

    $livingA = driftConcernTotal($ro, $built['A']);
    $outside = app(WorkCompletionAuthorization::class)->workOutsideApprovedSalesCents($ro);
    expect($outside)->toBe(8981)
        ->and(app(EstimateTotalsCalculator::class)->approvedTotalsForRead($ro)->totalCents())->toBe(90000);

    driftApprove($ro, number_format(($livingA) / 100, 2, '.', ''));
    $ro = $ro->fresh(['lines.concern', 'concerns']);

    expect(app(EstimateTotalsCalculator::class)->approvedTotalsForRead($ro)->totalCents())->toBe($livingA)
        ->and(app(WorkCompletionAuthorization::class)->workOutsideApprovedSalesCents($ro))->toBe(0)
        ->and(ApprovalEvent::query()->where('visit_id', $ro->id)->count())->toBe(2);
});

test('closed repair order keeps frozen authorized cents after living line mutation attempt', function () {
    driftConfigureOwnerShop(capCents: 4000);
    $built = driftBuildOwnerEstimate();
    $ro = $built['ro'];
    driftDecline($ro, $built['B']);
    driftApprove($ro, '900.00');
    $authorized = app(EstimateTotalsCalculator::class)->approvedTotalsForRead($ro->fresh(['lines.concern']))->totalCents();

    $ro->forceFill(['status' => RepairOrderStatus::Closed, 'closed_at' => now()])->save();
    $built['lines']['A-labor']->fresh()->update(['quantity' => '9.00', 'unit_price_cents' => 99900]);
    app(EstimateTotalsCalculator::class)->recalculateRepairOrder($ro->fresh(['lines', 'customer', 'concerns']));

    expect(app(EstimateTotalsCalculator::class)->approvedTotalsForRead($ro->fresh(['lines.concern']))->totalCents())->toBe($authorized)
        ->and((int) ApprovalEvent::query()->where('visit_id', $ro->id)->value('approved_amount_cents'))->toBe(90000);
});

// --- helpers ---------------------------------------------------------------------------

/**
 * @return array{0: RepairOrder, 1: RepairOrderLine, 2: int}
 */
function driftSinglePartShop(?int $feeCapCents, int $unitPriceCents, string $qty, bool $configureShop = true): array
{
    if ($configureShop) {
        ShopSettings::current()->update([
            'tax_enabled' => true,
            'tax_label' => 'Sales Tax',
            'default_tax_rate' => '8.000',
            'taxable_labor' => true,
            'taxable_parts' => true,
            'taxable_shop_fees' => false,
            'shop_fee_enabled' => true,
            'shop_fee_mode' => 'percent',
            'shop_fee_rate' => '5.000',
            'shop_fee_cap_cents' => $feeCapCents,
            'portal_signature_required' => false,
            'parts_matrices' => ShopSettings::DEFAULT_PARTS_MATRICES,
            'customer_types' => collect([
                ['name' => 'Retail', 'discount_type' => 'none', 'default_parts_matrix_key' => 'aft-parts'],
            ])->map(fn (array $row): array => ShopSettings::current()->normalizeStoredCustomerTypeRow($row))->all(),
        ]);
    }

    $customer = Customer::query()->create(['first_name' => 'Single', 'last_name' => 'Part', 'phone' => '555-'.random_int(1000, 9999), 'customer_type' => 'Retail']);
    $vehicle = Vehicle::query()->create(['customer_id' => $customer->id, 'year' => 2017, 'make' => 'Toyota', 'model' => 'Corolla']);
    $ro = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::WaitingApproval,
        'concern_summary' => 'Single part',
    ]);
    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $ro->id,
        'summary' => 'Part',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'billing_posture' => ConcernBillingPosture::CustomerPay,
        'position' => 1,
    ]);
    $part = RepairOrderLine::query()->create([
        'repair_order_id' => $ro->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Part,
        'description' => 'Part',
        'quantity' => $qty,
        'unit_price_cents' => $unitPriceCents,
        'part_cost_cents' => (int) round($unitPriceCents / 2),
    ]);
    app(EstimateTotalsCalculator::class)->recalculateRepairOrder($ro->fresh(['lines', 'customer', 'concerns']));
    driftApprove($ro, null);
    $authorized = app(EstimateTotalsCalculator::class)->approvedTotalsForRead($ro->fresh(['lines.concern']))->totalCents();

    return [$ro->fresh(), $part->fresh(), $authorized];
}

function driftConfigureOwnerShop(?int $capCents): void
{
    ShopSettings::current()->update([
        'labor_categories' => ShopSettings::DEFAULT_LABOR_CATEGORIES,
        'default_labor_rate_cents' => 16500,
        'tax_enabled' => true,
        'tax_label' => 'Sales Tax',
        'default_tax_rate' => '8.000',
        'taxable_labor' => true,
        'taxable_parts' => true,
        'taxable_shop_fees' => false,
        'shop_fee_enabled' => true,
        'shop_fee_mode' => 'percent',
        'shop_fee_rate' => '5.000',
        'shop_fee_flat_cents' => 2500,
        'shop_fee_cap_cents' => $capCents,
        'portal_signature_required' => false,
        'parts_matrices' => ShopSettings::DEFAULT_PARTS_MATRICES,
        'customer_types' => collect([
            ['name' => 'Retail', 'discount_type' => 'none', 'default_parts_matrix_key' => 'aft-parts'],
            ['name' => 'PartsDiscount10', 'discount_type' => 'parts', 'discount_amount' => '10.00', 'default_parts_matrix_key' => 'aft-parts'],
        ])->map(fn (array $row): array => ShopSettings::current()->normalizeStoredCustomerTypeRow($row))->all(),
        'commercial_pricing_profile_version' => 1,
    ]);
}

/** @return array{ro: RepairOrder, A: RepairOrderConcern, B: RepairOrderConcern, lines: array<string, RepairOrderLine>} */
function driftBuildOwnerEstimate(): array
{
    $customer = Customer::query()->create([
        'first_name' => 'Drift',
        'last_name' => 'Synthetic',
        'phone' => '555-0188',
        'email' => 'drift@example.test',
        'customer_type' => 'PartsDiscount10',
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2018,
        'make' => 'Toyota',
        'model' => 'Camry',
    ]);
    $ro = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::WaitingApproval,
        'concern_summary' => 'Authorized cent drift',
    ]);
    $A = RepairOrderConcern::query()->create([
        'repair_order_id' => $ro->id,
        'summary' => 'Front brakes (A)',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'billing_posture' => ConcernBillingPosture::CustomerPay,
        'position' => 1,
    ]);
    $B = RepairOrderConcern::query()->create([
        'repair_order_id' => $ro->id,
        'summary' => 'A/C service (B)',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'billing_posture' => ConcernBillingPosture::CustomerPay,
        'position' => 2,
    ]);

    $lines = [
        'A-labor' => driftStoreLabor($ro, $A, 'A labor', '2.50'),
        'A-part' => driftStorePart($ro, $A, 'A part', 43673),
        'B-labor' => driftStoreLabor($ro, $B, 'B labor', '1.00'),
        'B-part' => driftStorePart($ro, $B, 'B part', 11502),
    ];

    return ['ro' => $ro->fresh(), 'A' => $A->fresh(), 'B' => $B->fresh(), 'lines' => array_map(fn ($l) => $l->fresh(), $lines)];
}

function driftStoreLabor(RepairOrder $ro, RepairOrderConcern $concern, string $description, string $hours): RepairOrderLine
{
    test()->post(route('operations.repair-orders.lines.store', $ro), [
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor->value,
        'description' => $description,
        'labor_category_key' => 'mechanical',
        'labor_entered_hours' => $hours,
        'labor_adjustment' => 'normal',
        'labor_rate_overridden' => '0',
    ])->assertSessionHasNoErrors();

    return RepairOrderLine::query()->where('repair_order_id', $ro->id)->where('description', $description)->latest('id')->firstOrFail();
}

function driftStorePart(RepairOrder $ro, RepairOrderConcern $concern, string $description, int $sellCents): RepairOrderLine
{
    $line = RepairOrderLine::query()->create([
        'repair_order_id' => $ro->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Part,
        'description' => $description,
        'quantity' => '1.00',
        'unit_price_cents' => $sellCents,
        'part_cost_cents' => (int) round($sellCents / 2),
    ]);
    app(EstimateTotalsCalculator::class)->recalculateRepairOrder($ro->fresh(['lines', 'customer', 'concerns']));

    return $line->fresh();
}

function driftConcernTotal(RepairOrder $ro, RepairOrderConcern $concern): int
{
    return (int) RepairOrderLine::query()->where('repair_order_id', $ro->id)->where('repair_order_concern_id', $concern->id)->sum('total_cents');
}

function driftDecline(RepairOrder $ro, RepairOrderConcern $concern): void
{
    test()->patch(route('operations.repair-orders.concerns.disposition', [$ro, $concern]), [
        'disposition' => RepairOrderConcernDisposition::Declined->value,
    ])->assertRedirect();
}

function driftApprove(RepairOrder $ro, ?string $amount): void
{
    $ro = $ro->fresh(['concerns', 'lines.concern']);
    test()->post(route('operations.repair-orders.authorization.store', $ro), array_filter([
        'source' => ApprovalSource::Phone->value,
        'approved_by' => 'Drift Synthetic',
        'approved_amount' => $amount,
        RepairOrderConcurrency::FIELD => (int) $ro->estimate_version,
        RepairOrderAuthorizationStoreController::PRESENTED_CHANGES_FIELD => app(WorkCompletionAuthorization::class)->pendingChangesFingerprint($ro),
    ], fn ($v): bool => $v !== null))->assertRedirect();
}

function driftRaiseLabor(RepairOrder $ro, RepairOrderLine $line, string $hours): void
{
    test()->patch(route('operations.repair-orders.lines.update', [$ro, $line]), [
        'repair_order_concern_id' => $line->repair_order_concern_id,
        'type' => RepairOrderLineType::Labor->value,
        'description' => $line->description,
        'labor_category_key' => 'mechanical',
        'labor_entered_hours' => $hours,
        'labor_adjustment' => 'normal',
        'labor_rate_overridden' => '0',
    ])->assertSessionHasNoErrors();
}
