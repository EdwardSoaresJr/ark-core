<?php

use App\Ark\Operations\Customers\ApplyCustomerBillingClassChangeAction;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Documents\EstimateDocument;
use App\Ark\Operations\EstimatePricing\CommercialPricingProfile;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\RepairOrders\ConcernBillingPosture;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value));

    $settings = ShopSettings::current();
    $settings->update([
        'tax_enabled' => false,
        'shop_fee_enabled' => true,
        'shop_fee_mode' => 'percent',
        'shop_fee_rate' => '5.000',
        'shop_fee_flat_cents' => null,
        'shop_fee_cap_cents' => null,
        'taxable_shop_fees' => false,
        'customer_types' => collect([
            ['name' => 'Retail', 'shop_fees_enabled' => true, 'shop_fee_rate_override' => null, 'discount_type' => 'none'],
            ['name' => 'Military', 'shop_fees_enabled' => true, 'shop_fee_rate_override' => null, 'discount_type' => 'labor', 'discount_amount' => '10.00'],
            ['name' => 'Fleet', 'shop_fees_enabled' => true, 'shop_fee_rate_override' => '2.000', 'discount_type' => 'none'],
            ['name' => 'Warranty', 'shop_fees_enabled' => false, 'discount_type' => 'none', 'default_parts_matrix_key' => 'warranty-no-markup'],
        ])->map(fn (array $row): array => $settings->normalizeStoredCustomerTypeRow($row))->all(),
    ]);
});

/**
 * @param  array<string, array<string, mixed>>  $changes
 * @return array<string, mixed>
 */
function commercialFormPayload(array $changes = []): array
{
    $settings = ShopSettings::current();

    return [
        'customer_types' => collect($settings->storedCustomerTypeRows())
            ->map(function (array $row) use ($changes): array {
                return array_merge([
                    'name' => $row['name'],
                    'document_presentation_profile' => $row['document_presentation_profile'],
                    'pill_color' => $row['pill_color'] ?? 'slate',
                    'shop_fees_enabled' => ($row['shop_fees_enabled'] ?? true) ? '1' : '0',
                    'shop_fee_rate_override' => $row['shop_fee_rate_override'] ?? '',
                    'default_parts_matrix_key' => $row['default_parts_matrix_key'] ?? '',
                    'discount_type' => $row['discount_type'] ?? 'none',
                    'discount_amount' => $row['discount_amount'] ?? '',
                    'concern_billing_posture' => $row['concern_billing_posture'] ?? '',
                ], $changes[$row['name']] ?? []);
            })
            ->all(),
    ];
}

function commercialRepairOrder(string $customerType = 'Military'): RepairOrder
{
    $customer = Customer::query()->create([
        'first_name' => 'Synthetic',
        'last_name' => $customerType,
        'phone' => '555-0181',
        'customer_type' => $customerType,
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2019,
        'make' => 'Ford',
        'model' => 'F-150',
    ]);

    return RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Commercial profile synthetic',
    ]);
}

function commercialLabor(
    RepairOrder $repairOrder,
    string $summary,
    ConcernBillingPosture $posture = ConcernBillingPosture::CustomerPay,
    int $unitPriceCents = 10000,
): RepairOrderLine {
    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => $summary,
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'billing_posture' => $posture,
    ]);

    $line = RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Labor '.$summary.' '.uniqid(),
        'quantity' => '1.00',
        'unit_price_cents' => $unitPriceCents,
    ]);

    app(EstimateTotalsCalculator::class)->ensureFinancialTotalsAreCurrent(
        $repairOrder->fresh(['lines', 'customer', 'concerns']),
    );

    return $line->fresh();
}

test('existing RO keeps stamped Military 10% after settings change to 15%, including newly added work', function () {
    $repairOrder = commercialRepairOrder('Military');
    expect($repairOrder->commercial_pricing_profile)->not->toBeNull();

    $first = commercialLabor($repairOrder, 'First');
    expect($first->standing_discount_cents)->toBe(1000)
        ->and((int) $repairOrder->lines()->sum('standing_discount_cents'))->toBe(1000);

    $this->patch(route('operations.settings.shop.customer-types.update'), commercialFormPayload([
        'Military' => ['discount_amount' => '15.00', 'discount_type' => 'labor'],
    ]))->assertRedirect();

    expect(ShopSettings::current()->standingDiscountPolicy('Military')['percent'])->toBe('15.00');

    app(EstimateTotalsCalculator::class)->ensureFinancialTotalsAreCurrent(
        $repairOrder->fresh(['lines', 'customer', 'concerns']),
    );
    expect($first->fresh()->standing_discount_cents)->toBe(1000);

    $second = commercialLabor($repairOrder, 'Second after settings');
    expect($second->standing_discount_cents)->toBe(1000)
        ->and((int) $repairOrder->lines()->sum('standing_discount_cents'))->toBe(2000);

    $newer = commercialRepairOrder('Military');
    $newLine = commercialLabor($newer, 'Uses 15');
    expect($newLine->standing_discount_cents)->toBe(1500)
        ->and($newer->fresh()->commercial_pricing_profile['version'])
        ->toBeGreaterThan($repairOrder->fresh()->commercial_pricing_profile['version']);
});

test('billing-class fee override changes do not reprice stamped ROs', function () {
    $repairOrder = commercialRepairOrder('Fleet');
    $line = commercialLabor($repairOrder, 'Fleet labor', ConcernBillingPosture::Default);
    // Default posture + Fleet customer → class fee override 2% of 10000 = 200
    expect($line->shop_fee_cents)->toBe(200);

    $this->patch(route('operations.settings.shop.customer-types.update'), commercialFormPayload([
        'Fleet' => ['shop_fee_rate_override' => '8.000', 'shop_fees_enabled' => '1'],
    ]))->assertRedirect();

    app(EstimateTotalsCalculator::class)->ensureFinancialTotalsAreCurrent(
        $repairOrder->fresh(['lines', 'customer', 'concerns']),
    );
    expect($line->fresh()->shop_fee_cents)->toBe(200);

    $added = commercialLabor($repairOrder, 'More fleet', ConcernBillingPosture::Default);
    expect($added->shop_fee_cents)->toBe(200);

    $newer = commercialRepairOrder('Fleet');
    $newLine = commercialLabor($newer, 'New fleet fee', ConcernBillingPosture::Default);
    expect($newLine->shop_fee_cents)->toBe(800);
});

test('mixed Retail and Warranty concerns keep distinct commercial fee rules after settings change', function () {
    $repairOrder = commercialRepairOrder('Retail');
    $retail = commercialLabor($repairOrder, 'Retail scope', ConcernBillingPosture::CustomerPay);
    $warranty = commercialLabor($repairOrder, 'Warranty scope', ConcernBillingPosture::WarrantyOther);

    expect($retail->shop_fee_cents)->toBe(500) // shop 5%
        ->and($warranty->shop_fee_cents)->toBe(0);

    $this->patch(route('operations.settings.shop.customer-types.update'), commercialFormPayload([
        'Warranty' => ['shop_fees_enabled' => '1', 'shop_fee_rate_override' => '9.000'],
        'Retail' => ['shop_fee_rate_override' => '1.000', 'shop_fees_enabled' => '1'],
    ]))->assertRedirect();

    app(EstimateTotalsCalculator::class)->ensureFinancialTotalsAreCurrent(
        $repairOrder->fresh(['lines', 'customer', 'concerns']),
    );

    expect($retail->fresh()->shop_fee_cents)->toBe(500)
        ->and($warranty->fresh()->shop_fee_cents)->toBe(0);
});

test('closed ROs are not recalculated and keep amounts after billing class settings change', function () {
    $repairOrder = commercialRepairOrder('Military');
    $line = commercialLabor($repairOrder, 'Closed work');
    expect($line->standing_discount_cents)->toBe(1000);

    $repairOrder->forceFill(['status' => RepairOrderStatus::Closed, 'closed_at' => now()])->save();

    $this->patch(route('operations.settings.shop.customer-types.update'), commercialFormPayload([
        'Military' => ['discount_amount' => '25.00', 'discount_type' => 'labor'],
    ]))->assertRedirect();

    app(EstimateTotalsCalculator::class)->recalculateLivingRepairOrders();
    app(EstimateTotalsCalculator::class)->ensureFinancialTotalsAreCurrent($repairOrder->fresh());

    expect($line->fresh()->standing_discount_cents)->toBe(1000)
        ->and($repairOrder->fresh()->commercial_pricing_profile)->not->toBeNull();
});

test('billing classes settings save does not refresh open estimate snapshots', function () {
    $repairOrder = commercialRepairOrder('Military');
    commercialLabor($repairOrder, 'Documented');

    $document = EstimateDocument::query()->create([
        'repair_order_id' => $repairOrder->id,
        'document_number' => 1,
        'document_type' => 'estimate',
        'status' => 'draft',
        'snapshot_json' => ['marker' => 'pre-settings-change', 'schema_version' => 1],
    ]);

    $this->patch(route('operations.settings.shop.customer-types.update'), commercialFormPayload([
        'Military' => ['discount_amount' => '20.00', 'discount_type' => 'labor'],
    ]))->assertRedirect();

    expect($document->fresh()->snapshot_json['marker'] ?? null)->toBe('pre-settings-change');
});

test('explicit customer billing class change restamps and applies current commercial rules', function () {
    $repairOrder = commercialRepairOrder('Military');
    $line = commercialLabor($repairOrder, 'Was military');
    expect($line->standing_discount_cents)->toBe(1000);

    $this->patch(route('operations.settings.shop.customer-types.update'), commercialFormPayload([
        'Military' => ['discount_amount' => '10.00', 'discount_type' => 'labor'],
        'Retail' => ['discount_type' => 'labor', 'discount_amount' => '5.00'],
    ]))->assertRedirect();

    // Settings change alone must not move the stamped Military RO.
    app(EstimateTotalsCalculator::class)->ensureFinancialTotalsAreCurrent(
        $repairOrder->fresh(['lines', 'customer', 'concerns']),
    );
    expect($line->fresh()->standing_discount_cents)->toBe(1000);

    $customer = $repairOrder->customer;
    $customer->update(['customer_type' => 'Retail']);
    app(ApplyCustomerBillingClassChangeAction::class)->execute($customer->fresh(), 'Military', auth()->user());

    expect($line->fresh()->standing_discount_cents)->toBe(500)
        ->and($repairOrder->fresh()->commercial_pricing_profile)->not->toBeNull();
});

test('three successive commercial configurations stamp three independent RO profiles', function () {
    $first = commercialRepairOrder('Military');
    commercialLabor($first, 'v1');
    $v1 = $first->fresh()->commercial_pricing_profile['version'];

    $this->patch(route('operations.settings.shop.customer-types.update'), commercialFormPayload([
        'Military' => ['discount_amount' => '12.00', 'discount_type' => 'labor'],
    ]))->assertRedirect();

    $second = commercialRepairOrder('Military');
    commercialLabor($second, 'v2');
    $v2 = $second->fresh()->commercial_pricing_profile['version'];

    $this->patch(route('operations.settings.shop.customer-types.update'), commercialFormPayload([
        'Military' => ['discount_amount' => '18.00', 'discount_type' => 'labor'],
    ]))->assertRedirect();

    $third = commercialRepairOrder('Military');
    commercialLabor($third, 'v3');
    $v3 = $third->fresh()->commercial_pricing_profile['version'];

    expect($v2)->toBeGreaterThan($v1)
        ->and($v3)->toBeGreaterThan($v2)
        ->and(CommercialPricingProfile::tryFromStored($first->fresh()->commercial_pricing_profile)->standingDiscountPolicy('Military')['percent'])->toBe('10.00')
        ->and(CommercialPricingProfile::tryFromStored($second->fresh()->commercial_pricing_profile)->standingDiscountPolicy('Military')['percent'])->toBe('12.00')
        ->and(CommercialPricingProfile::tryFromStored($third->fresh()->commercial_pricing_profile)->standingDiscountPolicy('Military')['percent'])->toBe('18.00')
        ->and($first->lines()->sole()->fresh()->standing_discount_cents)->toBe(1000)
        ->and($second->lines()->sole()->fresh()->standing_discount_cents)->toBe(1200)
        ->and($third->lines()->sole()->fresh()->standing_discount_cents)->toBe(1800);
});

test('commercial pricing profile serializes from shop settings', function () {
    $profile = CommercialPricingProfile::fromShopSettings(ShopSettings::current());

    expect($profile->standingDiscountPolicy('Military'))->toBe(['type' => 'labor', 'percent' => '10.00'])
        ->and($profile->shopFeePolicyForCustomerType('Fleet'))->toBe(['enabled' => true, 'rate' => '2.000'])
        ->and($profile->shopFeePolicyForCustomerType('Warranty')['enabled'])->toBeFalse()
        ->and($profile->toArray()['schema'])->toBe(1);
});
