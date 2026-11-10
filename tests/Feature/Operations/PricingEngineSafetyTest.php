<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\EstimatePricing\CommercialPricingProfile;
use App\Ark\Operations\EstimatePricing\RepairOrderPricingContext;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Parts\CustomerPartPresentationProfile;
use App\Ark\Operations\Parts\CustomerPartPresentationProfileResolver;
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
        'customer_types' => collect([
            ['name' => 'Military', 'discount_type' => 'labor', 'discount_amount' => '10.00', 'document_presentation_profile' => 'retail'],
            ['name' => 'Retail', 'discount_type' => 'none', 'document_presentation_profile' => 'retail'],
        ])->map(fn (array $row): array => $settings->normalizeStoredCustomerTypeRow($row))->all(),
    ]);
});

function engineRo(string $type = 'Military'): RepairOrder
{
    $customer = Customer::query()->create([
        'first_name' => 'Engine',
        'last_name' => $type,
        'phone' => '555-0182',
        'customer_type' => $type,
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2018,
        'make' => 'Toyota',
        'model' => 'Tacoma',
    ]);

    return RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Pricing engine safety',
    ]);
}

function engineLabor(RepairOrder $ro, int $cents = 10000): RepairOrderLine
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

test('closed null-profile RO cannot be repriced from live settings', function () {
    $ro = engineRo('Military');
    $line = engineLabor($ro);
    expect($line->standing_discount_cents)->toBe(1000);

    // Simulate legacy closed RO without commercial stamp.
    $ro->forceFill([
        'commercial_pricing_profile' => null,
        'shop_fee_profile' => null,
        'status' => RepairOrderStatus::Closed,
        'closed_at' => now(),
    ])->saveQuietly();

    ShopSettings::current()->update([
        'customer_types' => collect([
            ['name' => 'Military', 'discount_type' => 'labor', 'discount_amount' => '50.00'],
            ['name' => 'Retail', 'discount_type' => 'none'],
        ])->map(fn (array $row): array => ShopSettings::current()->normalizeStoredCustomerTypeRow($row))->all(),
        'shop_fee_rate' => '25.000',
    ]);

    $context = RepairOrderPricingContext::for($ro->fresh());
    expect($context->terminal)->toBeTrue()
        ->and($context->allowsLiveCommercialFallback)->toBeFalse()
        ->and($context->commercialProfile())->toBeNull();

    app(EstimateTotalsCalculator::class)->recalculateRepairOrder($ro->fresh(['lines', 'customer', 'concerns']));
    app(EstimateTotalsCalculator::class)->ensureFinancialTotalsAreCurrent($ro->fresh());

    // Stored discount and fee cents remain authoritative; settings edits must not move them.
    expect($line->fresh()->standing_discount_cents)->toBe(1000)
        ->and($line->fresh()->shop_fee_cents)->toBe(450)
        ->and($line->fresh()->total_cents)->toBe(9450);
});

test('stamped parts matrix tiers survive shop matrix edits for new lines on old ROs', function () {
    $settings = ShopSettings::current();
    $matrices = $settings->partsMatrices();
    $defaultKey = $settings->defaultPartsMatrix()['key'];
    $original = collect($matrices)->firstWhere('key', $defaultKey);
    expect($original)->not->toBeNull();

    $ro = engineRo('Retail');
    $profile = CommercialPricingProfile::tryFromStored($ro->fresh()->commercial_pricing_profile);
    expect($profile)->not->toBeNull()
        ->and($profile->schema)->toBe(2)
        ->and($profile->partsMatrices)->not->toBeEmpty();

    $stampedMarkup = $profile->partsMatrixByKey($defaultKey)['rows'][0]['markup_percentage'] ?? null;

    // Mutate live matrix tiers dramatically.
    $mutated = collect($matrices)->map(function (array $matrix) use ($defaultKey): array {
        if ($matrix['key'] !== $defaultKey) {
            return $matrix;
        }
        $matrix['rows'] = [[
            'min_cost' => '0.00',
            'max_cost' => null,
            'markup_percentage' => '999.00',
            'sort_order' => 1,
        ]];

        return $matrix;
    })->all();
    $settings->update(['parts_matrices' => $mutated]);

    $resolved = $ro->fresh()->pricingContext()->partsMatrixByKey($defaultKey);
    expect($resolved['rows'][0]['markup_percentage'])->toBe($stampedMarkup)
        ->and($resolved['rows'][0]['markup_percentage'])->not->toBe('999.00');

    $suggestion = app(EstimateTotalsCalculator::class)->matrixSuggestionForCost(
        10000,
        $settings->fresh(),
        $defaultKey,
        $resolved,
    );
    $liveSuggestion = app(EstimateTotalsCalculator::class)->matrixSuggestionForCost(
        10000,
        ShopSettings::current(),
        $defaultKey,
    );
    expect($suggestion['suggested_price_cents'])->not->toBe($liveSuggestion['suggested_price_cents']);
});

test('document presentation resolves from frozen commercial profile in snapshots', function () {
    $ro = engineRo('Military');
    $profile = CommercialPricingProfile::fromShopSettings(ShopSettings::current());
    $snapshot = [
        'customer' => ['customer_type' => 'Military'],
        'commercial_pricing_profile' => $profile->toArray(),
    ];

    $resolved = app(CustomerPartPresentationProfileResolver::class)->forConcern(
        $snapshot,
        ['billing_posture' => ConcernBillingPosture::CustomerPay->value],
    );

    expect($resolved)->toBe(CustomerPartPresentationProfile::Retail);

    // Live settings change presentation for Military → fleet should not affect frozen snapshot path.
    ShopSettings::current()->update([
        'customer_types' => collect([
            ['name' => 'Military', 'discount_type' => 'labor', 'discount_amount' => '10.00', 'document_presentation_profile' => 'fleet'],
            ['name' => 'Retail', 'discount_type' => 'none', 'document_presentation_profile' => 'retail'],
        ])->map(fn (array $row): array => ShopSettings::current()->normalizeStoredCustomerTypeRow($row))->all(),
    ]);

    $again = app(CustomerPartPresentationProfileResolver::class)->forConcern(
        $snapshot,
        ['billing_posture' => ConcernBillingPosture::CustomerPay->value],
    );
    expect($again)->toBe(CustomerPartPresentationProfile::Retail);
});

test('billing classes settings activation is transactional and stamps matrices', function () {
    $ro = engineRo('Military');
    // Downgrade stamp to schema-1 shape (keys only).
    $slice = CommercialPricingProfile::tryFromStored($ro->commercial_pricing_profile);
    $ro->forceFill([
        'commercial_pricing_profile' => [
            'schema' => 1,
            'version' => $slice->version,
            'customer_types' => $slice->customerTypeRows(),
        ],
    ])->saveQuietly();

    $this->patch(route('operations.settings.shop.customer-types.update'), [
        'customer_types' => collect(ShopSettings::current()->storedCustomerTypeRows())
            ->map(fn (array $row): array => [
                'name' => $row['name'],
                'document_presentation_profile' => $row['document_presentation_profile'],
                'pill_color' => $row['pill_color'] ?? 'slate',
                'shop_fees_enabled' => '1',
                'shop_fee_rate_override' => '',
                'default_parts_matrix_key' => $row['default_parts_matrix_key'] ?? '',
                'discount_type' => $row['name'] === 'Military' ? 'labor' : 'none',
                'discount_amount' => $row['name'] === 'Military' ? '20.00' : '',
            ])->all(),
    ])->assertRedirect();

    $upgraded = CommercialPricingProfile::tryFromStored($ro->fresh()->commercial_pricing_profile);
    expect($upgraded)->not->toBeNull()
        ->and($upgraded->partsMatrices)->not->toBeEmpty()
        ->and($upgraded->standingDiscountPolicy('Military')['percent'])->toBe('10.00');
});

test('pricing context provenance exposes schema versions', function () {
    $ro = engineRo();
    $provenance = $ro->pricingContext()->provenance();

    expect($provenance['terminal'])->toBeFalse()
        ->and($provenance['commercial_schema'])->toBe(2)
        ->and($provenance['shop_fee_schema'])->toBe(1);
});
