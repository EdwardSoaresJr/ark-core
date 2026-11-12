<?php

use App\Ark\Operations\Approvals\ApprovalEvent;
use App\Ark\Operations\Approvals\ApprovalSource;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Documents\EstimateDocument;
use App\Ark\Operations\EstimatePricing\ShopFeeProfile;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Financial\FinancialDocumentType;
use App\Ark\Operations\Financial\GenerateInvoiceSnapshotAction;
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
    ShopSettings::current()->update([
        'labor_categories' => ShopSettings::DEFAULT_LABOR_CATEGORIES,
        'default_labor_rate_cents' => 10000,
        'tax_enabled' => false,
        'taxable_shop_fees' => false,
        'shop_fee_enabled' => true,
        'shop_fee_mode' => 'percent',
        'shop_fee_applies_to' => 'both',
        'shop_fee_rate' => '10.000',
        'shop_fee_flat_cents' => 2000,
        'shop_fee_cap_cents' => null,
        'portal_signature_required' => false,
    ]);
    $this->admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
    $this->actingAs($this->admin);
});

function appliesToRo(string $customerType = 'Retail'): RepairOrder
{
    $customer = Customer::query()->create([
        'first_name' => 'Synthetic',
        'last_name' => 'AppliesTo',
        'phone' => '555-0174',
        'customer_type' => $customerType,
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2018,
        'make' => 'Honda',
        'model' => 'Accord',
    ]);

    return RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Shop fee applies-to',
    ]);
}

/**
 * @param  list<array{type: RepairOrderLineType, cents: int}>  $lines
 */
function appliesToConcern(
    RepairOrder $repairOrder,
    string $summary,
    array $lines,
    ConcernBillingPosture $posture = ConcernBillingPosture::CustomerPay,
    RepairOrderConcernDisposition $disposition = RepairOrderConcernDisposition::Recommended,
): RepairOrderConcern {
    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => $summary,
        'disposition' => $disposition,
        'billing_posture' => $posture,
    ]);

    foreach ($lines as $index => $spec) {
        $type = $spec['type'];
        $cents = $spec['cents'];
        RepairOrderLine::query()->create([
            'repair_order_id' => $repairOrder->id,
            'repair_order_concern_id' => $concern->id,
            'type' => $type,
            'description' => $summary.' '.$type->value.' '.$index,
            'quantity' => '1.00',
            'unit_price_cents' => $cents,
            'subtotal_cents' => $cents,
            'tax_cents' => 0,
            'shop_fee_cents' => 0,
            'total_cents' => $cents,
            'labor_category_key' => $type === RepairOrderLineType::Labor ? 'mechanical' : null,
            'labor_entered_hours' => $type === RepairOrderLineType::Labor ? '1.00' : null,
        ]);
    }

    app(EstimateTotalsCalculator::class)->ensureFinancialTotalsAreCurrent(
        $repairOrder->fresh(['lines', 'concerns', 'customer']),
    );

    return $concern->fresh();
}

function configureAppliesTo(string $mode, string $appliesTo, ?string $rate = '10.000', ?string $flat = '20.00', ?string $cap = null): void
{
    test()->patch(route('operations.settings.shop.fees.update'), array_filter([
        'shop_fee_enabled' => '1',
        'shop_fee_mode' => $mode,
        'shop_fee_applies_to' => $appliesTo,
        'shop_fee_rate' => $rate,
        'shop_fee_flat' => $flat,
        'shop_fee_cap' => $cap,
    ], fn ($value) => $value !== null))->assertRedirect()->assertSessionHasNoErrors();
}

test('settings save stamps applies_to on new ROs as schema 3', function () {
    configureAppliesTo('percent', 'labor', '8.000', null, null);

    $settings = ShopSettings::current()->fresh();
    expect($settings->shopFeeAppliesTo())->toBe('labor');

    $ro = appliesToRo();
    expect($ro->fresh()->shop_fee_profile['applies_to'])->toBe('labor')
        ->and($ro->fresh()->shop_fee_profile['schema'])->toBe(3)
        ->and($ro->fresh()->shop_fee_profile['mode'])->toBe('percent');
});

test('percentage fees honor labor only, parts only, and both', function (string $appliesTo, int $expectedFeeCents) {
    configureAppliesTo('percent', $appliesTo, '10.000', null, null);

    $ro = appliesToRo();
    appliesToConcern($ro, 'Mixed', [
        ['type' => RepairOrderLineType::Labor, 'cents' => 10000],
        ['type' => RepairOrderLineType::Part, 'cents' => 5000],
        ['type' => RepairOrderLineType::Package, 'cents' => 2000],
        ['type' => RepairOrderLineType::Sublet, 'cents' => 8000],
        ['type' => RepairOrderLineType::Fee, 'cents' => 1000],
    ]);

    expect((int) $ro->lines()->sum('shop_fee_cents'))->toBe($expectedFeeCents);
})->with([
    // Labor+Package @ 10% = 1200; Sublet/Fee never eligible
    'labor only' => ['labor', 1200],
    // Parts @ 10% = 500
    'parts only' => ['parts', 500],
    // Labor+Part+Package @ 10% = 1700
    'both' => ['both', 1700],
]);

test('flat per RO charges once when a qualifying line exists for the selection', function (string $appliesTo, int $expectedFeeCents) {
    configureAppliesTo('flat', $appliesTo, null, '20.00', null);

    $ro = appliesToRo();
    appliesToConcern($ro, 'Parts only concern', [
        ['type' => RepairOrderLineType::Part, 'cents' => 5000],
    ]);

    expect((int) $ro->lines()->sum('shop_fee_cents'))->toBe($expectedFeeCents);
})->with([
    'labor only with only parts' => ['labor', 0],
    'parts only with only parts' => ['parts', 2000],
    'both with only parts' => ['both', 2000],
]);

test('flat per RO still charges once across multiple qualifying lines', function () {
    configureAppliesTo('flat', 'both', null, '20.00', null);

    $ro = appliesToRo();
    appliesToConcern($ro, 'A', [
        ['type' => RepairOrderLineType::Labor, 'cents' => 10000],
        ['type' => RepairOrderLineType::Part, 'cents' => 5000],
    ]);
    appliesToConcern($ro, 'B', [
        ['type' => RepairOrderLineType::Labor, 'cents' => 10000],
    ]);

    expect((int) $ro->lines()->sum('shop_fee_cents'))->toBe(2000);
});

test('flat per concern charges once per concern with a qualifying line', function (string $appliesTo, int $expectedFeeCents) {
    configureAppliesTo('per_concern', $appliesTo, null, '15.00', null);

    $ro = appliesToRo();
    appliesToConcern($ro, 'Labor concern', [
        ['type' => RepairOrderLineType::Labor, 'cents' => 10000],
        ['type' => RepairOrderLineType::Labor, 'cents' => 10000],
    ]);
    appliesToConcern($ro, 'Parts concern', [
        ['type' => RepairOrderLineType::Part, 'cents' => 5000],
        ['type' => RepairOrderLineType::Part, 'cents' => 5000],
    ]);
    appliesToConcern($ro, 'Mixed concern', [
        ['type' => RepairOrderLineType::Labor, 'cents' => 10000],
        ['type' => RepairOrderLineType::Part, 'cents' => 5000],
    ]);

    expect((int) $ro->lines()->sum('shop_fee_cents'))->toBe($expectedFeeCents);
})->with([
    // Labor concern + mixed = 2 × 1500
    'labor only' => ['labor', 3000],
    // Parts concern + mixed = 2 × 1500
    'parts only' => ['parts', 3000],
    // All three concerns = 3 × 1500
    'both' => ['both', 4500],
]);

test('per-concern cap still limits aggregate fee under applies_to', function () {
    configureAppliesTo('per_concern', 'both', null, '15.00', '25.00');

    $ro = appliesToRo();
    appliesToConcern($ro, 'One', [['type' => RepairOrderLineType::Labor, 'cents' => 10000]]);
    appliesToConcern($ro, 'Two', [['type' => RepairOrderLineType::Part, 'cents' => 5000]]);

    expect((int) $ro->lines()->sum('shop_fee_cents'))->toBe(2500);
});

test('percentage cap still limits aggregate fee under applies_to', function () {
    configureAppliesTo('percent', 'both', '50.000', null, '10.00');

    $ro = appliesToRo();
    appliesToConcern($ro, 'Big', [
        ['type' => RepairOrderLineType::Labor, 'cents' => 10000],
        ['type' => RepairOrderLineType::Part, 'cents' => 10000],
    ]);

    expect((int) $ro->lines()->sum('shop_fee_cents'))->toBe(1000);
});

test('warranty and internal postures remain fee-excluded under applies_to', function (ConcernBillingPosture $posture) {
    configureAppliesTo('percent', 'both', '10.000', null, null);

    $ro = appliesToRo();
    appliesToConcern($ro, $posture->value, [
        ['type' => RepairOrderLineType::Labor, 'cents' => 10000],
        ['type' => RepairOrderLineType::Part, 'cents' => 5000],
    ], $posture);

    expect((int) $ro->lines()->sum('shop_fee_cents'))->toBe(0);
})->with([
    'warranty' => [ConcernBillingPosture::WarrantyOther],
    'internal' => [ConcernBillingPosture::Internal],
]);

test('closed RO shop fees stay immutable when applies_to changes', function () {
    configureAppliesTo('percent', 'both', '10.000', null, null);

    $ro = appliesToRo();
    appliesToConcern($ro, 'Closed', [
        ['type' => RepairOrderLineType::Labor, 'cents' => 10000],
        ['type' => RepairOrderLineType::Part, 'cents' => 5000],
    ]);
    expect((int) $ro->lines()->sum('shop_fee_cents'))->toBe(1500);

    $ro->update([
        'status' => RepairOrderStatus::Closed,
        'closed_at' => now(),
    ]);

    configureAppliesTo('percent', 'labor', '10.000', null, null);
    app(EstimateTotalsCalculator::class)->ensureFinancialTotalsAreCurrent(
        $ro->fresh(['lines', 'concerns', 'customer']),
    );

    expect($ro->fresh()->shop_fee_profile['applies_to'])->toBe('both')
        ->and((int) $ro->lines()->sum('shop_fee_cents'))->toBe(1500)
        ->and((int) $ro->lines()->sum('total_cents'))->toBe(16500);
});

test('billing class shop fee exemption still wins under applies_to', function () {
    // Wholesale posture maps to the Wholesale billing class (not a warranty/internal waive).
    $settings = ShopSettings::current();
    $types = collect($settings->customer_types ?: ShopSettings::DEFAULT_CUSTOMER_TYPES)
        ->map(fn (array $type): array => ($type['name'] ?? '') === 'Wholesale'
            ? [...$type, 'shop_fees_enabled' => false]
            : $type)
        ->all();
    $settings->update(['customer_types' => $types]);

    configureAppliesTo('percent', 'both', '10.000', null, null);

    $ro = appliesToRo('Wholesale');
    appliesToConcern($ro, 'Exempt wholesale', [
        ['type' => RepairOrderLineType::Labor, 'cents' => 10000],
        ['type' => RepairOrderLineType::Part, 'cents' => 5000],
    ], ConcernBillingPosture::Wholesale);

    expect((int) $ro->lines()->sum('shop_fee_cents'))->toBe(0);
});

test('schema 1 and schema 2 profiles without applies_to keep historical Labor+Part+Package base', function () {
    $ro = appliesToRo();
    $ro->update([
        'shop_fee_profile' => [
            'enabled' => true,
            'mode' => 'percent',
            'rate' => '10.000',
            'flat_cents' => null,
            'cap_cents' => null,
            'taxable' => false,
            'schema' => 1,
            // no applies_to key
        ],
    ]);

    appliesToConcern($ro, 'Legacy', [
        ['type' => RepairOrderLineType::Labor, 'cents' => 10000],
        ['type' => RepairOrderLineType::Part, 'cents' => 5000],
        ['type' => RepairOrderLineType::Package, 'cents' => 2000],
        ['type' => RepairOrderLineType::Sublet, 'cents' => 8000],
    ]);

    $profile = ShopFeeProfile::tryFromStored($ro->fresh()->shop_fee_profile);
    expect($profile?->schema)->toBe(1)
        ->and($profile?->appliesTo)->toBe('both')
        ->and((int) $ro->lines()->sum('shop_fee_cents'))->toBe(1700);
});

test('schema 2 per_concern without applies_to keeps historical both base', function () {
    $ro = appliesToRo();
    $ro->update([
        'shop_fee_profile' => [
            'enabled' => true,
            'mode' => 'per_concern',
            'rate' => '0',
            'flat_cents' => 1500,
            'cap_cents' => null,
            'taxable' => false,
            'schema' => 2,
        ],
    ]);

    appliesToConcern($ro, 'Labor', [['type' => RepairOrderLineType::Labor, 'cents' => 10000]]);
    appliesToConcern($ro, 'Parts', [['type' => RepairOrderLineType::Part, 'cents' => 5000]]);

    expect((int) $ro->lines()->sum('shop_fee_cents'))->toBe(3000);
});

test('changing shop applies_to does not reprice an already stamped open RO', function () {
    configureAppliesTo('percent', 'both', '10.000', null, null);
    $ro = appliesToRo();
    appliesToConcern($ro, 'Stamped', [
        ['type' => RepairOrderLineType::Labor, 'cents' => 10000],
        ['type' => RepairOrderLineType::Part, 'cents' => 5000],
    ]);
    expect((int) $ro->lines()->sum('shop_fee_cents'))->toBe(1500);

    configureAppliesTo('percent', 'labor', '10.000', null, null);
    app(EstimateTotalsCalculator::class)->ensureFinancialTotalsAreCurrent(
        $ro->fresh(['lines', 'concerns', 'customer']),
    );

    expect($ro->fresh()->shop_fee_profile['applies_to'])->toBe('both')
        ->and((int) $ro->lines()->sum('shop_fee_cents'))->toBe(1500);
});

test('supplemental authorization still required when labor-only fee rises after approval', function () {
    configureAppliesTo('per_concern', 'labor', null, '15.00', '40.00');

    $ro = appliesToRo();
    $ro->update(['status' => RepairOrderStatus::WaitingApproval]);
    appliesToConcern($ro, 'One', [['type' => RepairOrderLineType::Labor, 'cents' => 10000]]);
    appliesToConcern($ro, 'Two', [['type' => RepairOrderLineType::Labor, 'cents' => 10000]]);

    expect((int) $ro->lines()->sum('shop_fee_cents'))->toBe(3000);

    $payload = [
        'source' => ApprovalSource::InPerson->value,
        'approved_by' => 'Synthetic AppliesTo',
        RepairOrderConcurrency::FIELD => (int) $ro->fresh()->estimate_version,
        RepairOrderAuthorizationStoreController::PRESENTED_CHANGES_FIELD => app(WorkCompletionAuthorization::class)
            ->pendingChangesFingerprint($ro->fresh(['lines.concern', 'concerns'])),
    ];
    $this->post(route('operations.repair-orders.authorization.store', $ro), $payload)
        ->assertRedirect();

    $calculator = app(EstimateTotalsCalculator::class);
    $approvedBefore = $calculator->approvedTotalsForRead($ro->fresh(['lines.concern', 'concerns']))->totalCents();
    expect(ApprovalEvent::query()->where('visit_id', $ro->id)->count())->toBe(1)
        ->and($calculator->approvedTotalsForRead($ro->fresh(['lines.concern', 'concerns']))->allocatedShopFeesCents())->toBe(3000);

    // Parts-only third concern must not raise labor-only fee or approved dollars.
    appliesToConcern($ro, 'Parts only', [['type' => RepairOrderLineType::Part, 'cents' => 9000]]);
    $ro = $ro->fresh(['lines.concern', 'concerns']);
    $calculator->ensureFinancialTotalsAreCurrent($ro);
    $ro = $ro->fresh(['lines.concern', 'concerns']);

    expect($calculator->approvedTotalsForRead($ro)->allocatedShopFeesCents())->toBe(3000)
        ->and($calculator->approvedTotalsForRead($ro)->totalCents())->toBe($approvedBefore)
        ->and((int) $ro->lines()->sum('shop_fee_cents'))->toBe(3000);
});

test('taxable shop fees and invoice totals reconcile under parts-only applies_to', function () {
    ShopSettings::current()->update([
        'tax_enabled' => true,
        'tax_label' => 'Sales Tax',
        'default_tax_rate' => '10.000',
        'taxable_labor' => false,
        'taxable_parts' => true,
        'taxable_shop_fees' => true,
    ]);
    configureAppliesTo('percent', 'parts', '10.000', null, null);

    $ro = appliesToRo();
    $ro->update(['status' => RepairOrderStatus::WaitingApproval]);
    appliesToConcern($ro, 'Taxable parts', [
        ['type' => RepairOrderLineType::Labor, 'cents' => 10000],
        ['type' => RepairOrderLineType::Part, 'cents' => 5000],
    ]);

    $ro = $ro->fresh(['lines', 'concerns', 'customer']);
    $fee = (int) $ro->lines()->sum('shop_fee_cents');
    $tax = (int) $ro->lines()->sum('tax_cents');
    $total = (int) $ro->lines()->sum('total_cents');

    // Fee on parts only: 10% of 5000 = 500. Tax on part subtotal + fee = 10% of 5500 = 550.
    expect($fee)->toBe(500)
        ->and($tax)->toBe(550)
        ->and($total)->toBe(10000 + 5000 + 500 + 550);

    $payload = [
        'source' => ApprovalSource::InPerson->value,
        'approved_by' => 'Synthetic AppliesTo Tax',
        RepairOrderConcurrency::FIELD => (int) $ro->estimate_version,
        RepairOrderAuthorizationStoreController::PRESENTED_CHANGES_FIELD => app(WorkCompletionAuthorization::class)
            ->pendingChangesFingerprint($ro->fresh(['lines.concern', 'concerns'])),
    ];
    $this->post(route('operations.repair-orders.authorization.store', $ro), $payload)
        ->assertRedirect();

    $ro = $ro->fresh(['lines.concern', 'concerns', 'customer', 'vehicle']);
    $ro->update(['status' => RepairOrderStatus::ReadyPickup]);
    $calculator = app(EstimateTotalsCalculator::class);
    $approvedTotal = $calculator->totalsForApprovedWork($ro->fresh(['lines.concern']))->totalCents();
    $approvedFees = $calculator->approvedTotalsForRead($ro->fresh(['lines.concern']))->allocatedShopFeesCents();

    $invoice = app(GenerateInvoiceSnapshotAction::class)->execute($ro->fresh(['lines.concern', 'concerns', 'customer', 'vehicle']));
    expect($invoice)->toBeInstanceOf(EstimateDocument::class)
        ->and($invoice->document_type)->toBe(FinancialDocumentType::Invoice)
        ->and((int) data_get($invoice->snapshot_json, 'totals.total_cents'))->toBe($approvedTotal)
        ->and((int) data_get($invoice->snapshot_json, 'totals.fees_cents'))->toBe($approvedFees)
        ->and($approvedFees)->toBe(500)
        ->and($approvedTotal)->toBe($total);
});

test('fees settings UI exposes Apply shop fees to for all methods', function () {
    ShopSettings::current()->update(['shop_fee_enabled' => true, 'shop_fee_applies_to' => 'parts']);

    $html = $this->get(route('operations.settings.shop.edit', [
        'section' => 'financial',
        'financial-tab' => 'shop-fees',
    ]))->assertOk()->getContent();

    expect($html)
        ->toContain('Apply shop fees to')
        ->toContain('name="shop_fee_applies_to" value="labor"')
        ->toContain('name="shop_fee_applies_to" value="parts"')
        ->toContain('name="shop_fee_applies_to" value="both"')
        ->toContain('data-settings-fees-applies-to')
        ->toContain('Package lines count with labor');
});
