<?php

use App\Ark\Operations\Customers\ApplyCustomerBillingClassChangeAction;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\EstimatePricing\CommercialPricingProfile;
use App\Ark\Operations\EstimatePricing\RepairOrderPricingContext;
use App\Ark\Operations\EstimatePricing\TaxProfile;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\RepairOrders\ConcernBillingPosture;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLinePricing;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value));

    ShopSettings::current()->update([
        'tax_enabled' => true,
        'tax_label' => 'Sales Tax',
        'default_tax_rate' => '8.000',
        'taxable_labor' => true,
        'taxable_parts' => true,
        'taxable_shop_fees' => false,
        'shop_fee_enabled' => false,
        'parts_matrices' => ShopSettings::DEFAULT_PARTS_MATRICES,
        'customer_types' => collect([
            ['name' => 'Retail', 'discount_type' => 'none', 'default_parts_matrix_key' => 'aft-parts'],
            ['name' => 'Military', 'discount_type' => 'labor', 'discount_amount' => '10.00', 'default_parts_matrix_key' => 'aft-parts'],
        ])->map(fn (array $row): array => ShopSettings::current()->normalizeStoredCustomerTypeRow($row))->all(),
        'commercial_pricing_profile_version' => 1,
    ]);
});

function safetyCustomer(string $type = 'Retail'): Customer
{
    return Customer::query()->create([
        'first_name' => 'Safety',
        'last_name' => $type,
        'phone' => '555-0183',
        'customer_type' => $type,
    ]);
}

function safetyRo(Customer $customer, array $overrides = []): RepairOrder
{
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2019,
        'make' => 'Honda',
        'model' => 'Civic',
    ]);

    return RepairOrder::query()->create(array_merge([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Matrix/tax safety',
    ], $overrides));
}

function safetyLabor(RepairOrder $ro, int $cents = 10000): RepairOrderLine
{
    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $ro->id,
        'summary' => 'Work '.uniqid(),
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'billing_posture' => ConcernBillingPosture::CustomerPay,
    ]);

    $line = RepairOrderLine::query()->create([
        'repair_order_id' => $ro->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Labor '.uniqid(),
        'quantity' => '1.00',
        'unit_price_cents' => $cents,
    ]);

    app(EstimateTotalsCalculator::class)->ensureFinancialTotalsAreCurrent(
        $ro->fresh(['lines', 'customer', 'concerns']),
    );

    return $line->fresh();
}

function safetyPart(RepairOrder $ro, int $costCents, int $sellCents, ?string $matrixKey = 'aft-parts'): RepairOrderLine
{
    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $ro->id,
        'summary' => 'Parts '.uniqid(),
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'billing_posture' => ConcernBillingPosture::CustomerPay,
    ]);

    $line = RepairOrderLine::query()->create([
        'repair_order_id' => $ro->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Part,
        'description' => 'Part '.uniqid(),
        'quantity' => '1.00',
        'unit_price_cents' => $sellCents,
        'part_cost_cents' => $costCents,
        'pricing_mode' => 'matrix',
        'pricing_matrix_key' => $matrixKey,
        'pricing_matrix_name' => $matrixKey,
        'matrix_applied' => true,
    ]);

    app(EstimateTotalsCalculator::class)->ensureFinancialTotalsAreCurrent(
        $ro->fresh(['lines', 'customer', 'concerns']),
    );

    return $line->fresh();
}

test('new RO stamps tax profile and keeps it when tax settings change', function () {
    $ro = safetyRo(safetyCustomer());
    expect($ro->tax_profile)->not->toBeNull()
        ->and($ro->tax_profile['rate'])->toBe('8.000')
        ->and($ro->tax_profile['taxable_labor'])->toBeTrue();

    $line = safetyLabor($ro, 10000);
    expect($line->tax_cents)->toBe(800);

    $this->patch(route('operations.settings.shop.tax.update'), [
        'tax_enabled' => '1',
        'tax_label' => 'Sales Tax',
        'default_tax_rate' => '12.500',
        'taxable_labor' => '1',
        'taxable_parts' => '1',
    ])->assertRedirect();

    app(EstimateTotalsCalculator::class)->recalculateRepairOrder($ro->fresh(['lines', 'customer', 'concerns']));
    $line = $line->fresh();

    expect($ro->fresh()->tax_profile['rate'])->toBe('8.000')
        ->and($line->tax_cents)->toBe(800);

    $newer = safetyRo(safetyCustomer());
    expect($newer->tax_profile['rate'])->toBe('12.500');
    $newerLine = safetyLabor($newer, 10000);
    expect($newerLine->tax_cents)->toBe(1250);
});

test('tax settings save stamps living null-profile ROs and does not reprice them', function () {
    $ro = safetyRo(safetyCustomer());
    $line = safetyLabor($ro, 10000);
    expect($line->tax_cents)->toBe(800);

    $ro->forceFill(['tax_profile' => null])->saveQuietly();

    $this->patch(route('operations.settings.shop.tax.update'), [
        'tax_enabled' => '1',
        'tax_label' => 'Sales Tax',
        'default_tax_rate' => '15.000',
        'taxable_labor' => '1',
        'taxable_parts' => '1',
    ])->assertRedirect();

    $ro = $ro->fresh();
    expect($ro->tax_profile)->not->toBeNull()
        ->and($ro->tax_profile['rate'])->toBe('8.000');

    app(EstimateTotalsCalculator::class)->recalculateRepairOrder($ro->fresh(['lines', 'customer', 'concerns']));
    expect($line->fresh()->tax_cents)->toBe(800);
});

test('closed RO tax amounts stay put when tax settings change', function () {
    $ro = safetyRo(safetyCustomer());
    $line = safetyLabor($ro, 10000);
    $taxBefore = $line->tax_cents;

    $ro->forceFill([
        'status' => RepairOrderStatus::Closed,
        'closed_at' => now(),
    ])->saveQuietly();

    $this->patch(route('operations.settings.shop.tax.update'), [
        'tax_enabled' => '1',
        'tax_label' => 'Sales Tax',
        'default_tax_rate' => '20.000',
        'taxable_labor' => '1',
        'taxable_parts' => '1',
    ])->assertRedirect();

    app(EstimateTotalsCalculator::class)->recalculateRepairOrder($ro->fresh(['lines', 'customer', 'concerns']));
    expect($line->fresh()->tax_cents)->toBe($taxBefore)
        ->and($ro->fresh()->tax_profile['rate'])->toBe('8.000');
});

test('archiving a parts matrix preserves definitions and does not reprice stamped ROs', function () {
    $ro = safetyRo(safetyCustomer());
    expect($ro->commercial_pricing_profile['parts_matrices'] ?? [])->not->toBeEmpty();

    $filtersBefore = collect($ro->commercial_pricing_profile['parts_matrices'])->firstWhere('key', 'filters');
    expect($filtersBefore)->not->toBeNull();

    $part = safetyPart($ro, 1000, 2500, 'filters');
    $sellBefore = $part->unit_price_cents;

    $this->delete(route('operations.settings.shop.parts-matrices.destroy', 'filters'), [
        'confirm_name' => 'Filters',
    ])->assertRedirect(route('operations.settings.shop.edit', ['section' => 'financial']));

    $settings = ShopSettings::current();
    expect(collect($settings->partsMatrices())->pluck('key')->all())->not->toContain('filters')
        ->and(collect($settings->parts_matrices)->firstWhere('key', 'filters')['is_active'])->toBeFalse();

    $ctx = RepairOrderPricingContext::for($ro->fresh());
    $matrix = $ctx->partsMatrixByKey('filters');
    expect($matrix)->not->toBeNull()
        ->and($matrix['rows'])->toBe($filtersBefore['rows']);

    expect($part->fresh()->unit_price_cents)->toBe($sellBefore);
});

test('explicit matrix created after RO open fails visibly', function () {
    $ro = safetyRo(safetyCustomer());
    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $ro->id,
        'summary' => 'Brake pads',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'billing_posture' => ConcernBillingPosture::CustomerPay,
    ]);

    // Add a brand-new shop matrix after the RO was stamped.
    $matrices = ShopSettings::current()->partsMatrices();
    $matrices[] = [
        'key' => 'post-open-matrix',
        'name' => 'Post Open',
        'is_default' => false,
        'rows' => [
            ['min_cost' => '0.00', 'max_cost' => null, 'markup_percentage' => '100.00', 'sort_order' => 0],
        ],
    ];

    $this->patch(route('operations.settings.shop.parts-matrices.update', 'aft-parts'), [
        'default_parts_matrix_key' => 'aft-parts',
        'parts_matrices' => $matrices,
    ])->assertRedirect();

    expect(collect(ShopSettings::current()->partsMatrices())->pluck('key')->all())->toContain('post-open-matrix');

    $pricing = app(RepairOrderLinePricing::class);

    expect(fn () => $pricing->previewFor([
        'repair_order_concern_id' => $concern->id,
        'part_cost' => '10.00',
        'pricing_mode' => 'matrix',
        'pricing_matrix_key' => 'post-open-matrix',
        'pricing_matrix_explicit' => true,
    ], $ro->fresh(['customer', 'concerns'])))
        ->toThrow(HttpException::class);
});

test('new parts on old RO use stamped matrix tiers not later shop edits', function () {
    $ro = safetyRo(safetyCustomer());
    $stamp = CommercialPricingProfile::tryFromStored($ro->commercial_pricing_profile);
    $aft = $stamp->partsMatrixExact('aft-parts');
    expect($aft)->not->toBeNull();

    // Mutate live shop matrix heavily after RO open.
    $matrices = collect(ShopSettings::current()->partsMatrices())
        ->map(function (array $matrix): array {
            if ($matrix['key'] !== 'aft-parts') {
                return $matrix;
            }
            $matrix['rows'] = [
                ['min_cost' => '0.00', 'max_cost' => null, 'markup_percentage' => '900.00', 'sort_order' => 0],
            ];

            return $matrix;
        })
        ->values()
        ->all();

    $this->patch(route('operations.settings.shop.parts-matrices.update', 'aft-parts'), [
        'default_parts_matrix_key' => 'aft-parts',
        'parts_matrices' => $matrices,
    ])->assertRedirect();

    $preview = app(RepairOrderLinePricing::class)->previewFor([
        'repair_order_concern_id' => RepairOrderConcern::query()->create([
            'repair_order_id' => $ro->id,
            'summary' => 'Oil filter',
            'disposition' => RepairOrderConcernDisposition::Recommended,
            'billing_posture' => ConcernBillingPosture::CustomerPay,
        ])->id,
        'part_cost' => '10.00',
        'pricing_mode' => 'matrix',
        'pricing_matrix_key' => 'aft-parts',
        'pricing_matrix_explicit' => true,
    ], $ro->fresh(['customer', 'concerns']));

    $liveSuggestion = app(EstimateTotalsCalculator::class)->matrixSuggestionForCost(
        1000,
        ShopSettings::current(),
        'aft-parts',
    );
    expect($preview['pricing_matrix_key'])->toBe('aft-parts')
        ->and($preview['matrix_suggested_price_cents'])->not->toBe($liveSuggestion['suggested_price_cents']);
});

test('repeated recalculation is deterministic under frozen tax and commercial rules', function () {
    $ro = safetyRo(safetyCustomer('Military'));
    safetyLabor($ro, 10000);
    safetyPart($ro, 2000, 5000);

    $calc = app(EstimateTotalsCalculator::class);
    $first = $calc->totalsFor($ro->fresh(['lines.concern', 'customer', 'concerns']))->totalCents();

    ShopSettings::current()->update([
        'default_tax_rate' => '25.000',
        'taxable_labor' => false,
    ]);

    $calc->recalculateRepairOrder($ro->fresh(['lines', 'customer', 'concerns']));
    $second = $calc->totalsFor($ro->fresh(['lines.concern', 'customer', 'concerns']))->totalCents();
    $calc->recalculateRepairOrder($ro->fresh(['lines', 'customer', 'concerns']));
    $third = $calc->totalsFor($ro->fresh(['lines.concern', 'customer', 'concerns']))->totalCents();

    expect($second)->toBe($first)->and($third)->toBe($first);
});

test('explicit billing class change does not invent a higher approved bill', function () {
    $customer = safetyCustomer('Retail');
    $ro = safetyRo($customer);
    $line = safetyLabor($ro, 10000);

    $concern = $line->concern;
    $concern->forceFill(['disposition' => RepairOrderConcernDisposition::Approved])->save();

    $priorApproved = app(EstimateTotalsCalculator::class)->approvedTotalsForRead(
        $ro->fresh(['lines.concern', 'customer', 'concerns']),
    )->totalCents();

    $customer->forceFill(['customer_type' => 'Military'])->save();
    app(ApplyCustomerBillingClassChangeAction::class)->execute($customer, 'Retail', auth()->user());

    $ro = $ro->fresh(['lines.concern', 'customer', 'concerns']);
    $approvedRead = app(EstimateTotalsCalculator::class)->approvedTotalsForRead($ro)->totalCents();

    // Military labor discount lowers the estimate; approved/invoice authority must not rise above prior.
    expect($approvedRead)->toBeLessThanOrEqual($priorApproved);
});

test('taxable and exempt bases follow the stamped tax profile', function () {
    $ro = safetyRo(safetyCustomer());
    $ro->forceFill([
        'tax_profile' => (new TaxProfile(
            enabled: true,
            label: 'Sales Tax',
            rate: '10.000',
            taxableLabor: false,
            taxableParts: true,
            taxableShopFees: false,
        ))->toArray(),
    ])->saveQuietly();

    $labor = safetyLabor($ro, 10000);
    $part = safetyPart($ro, 1000, 2000);

    expect($labor->fresh()->tax_cents)->toBe(0)
        ->and($part->fresh()->tax_cents)->toBe(200);
});
