<?php

use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value));

    $settings = ShopSettings::current();
    $settings->update([
        'tax_enabled' => false,
        'shop_fee_enabled' => false,
        'customer_types' => collect([
            ['name' => 'Retail', 'discount_type' => 'none', 'discount_amount' => null],
            ['name' => 'Fleet', 'discount_type' => 'parts', 'discount_amount' => '5.00'],
            ['name' => 'Warranty', 'discount_type' => 'none', 'discount_amount' => null],
            ['name' => 'Military', 'discount_type' => 'labor', 'discount_amount' => '15.00'],
            ['name' => 'Veteran', 'discount_type' => 'labor', 'discount_amount' => '15.00'],
        ])->map(fn (array $row): array => $settings->normalizeStoredCustomerTypeRow($row))->all(),
    ]);
});

/**
 * The billing class form as the settings page posts it: fleet and warranty rows
 * carry no discount fields, standard rows carry the saved discount override.
 *
 * @param  array<string, array<string, mixed>>  $changes
 * @return array<string, mixed>
 */
function blankDiscountFormPayload(array $changes = []): array
{
    $settings = ShopSettings::current();

    return [
        'customer_types' => collect($settings->storedCustomerTypeRows())
            ->map(function (array $row) use ($settings, $changes): array {
                $profile = $settings->customerTagBillingProfile($row['name']);
                $posted = [
                    'name' => $row['name'],
                    'document_presentation_profile' => $row['document_presentation_profile'],
                    'pill_color' => $row['pill_color'],
                    'shop_fees_enabled' => $row['shop_fees_enabled'] ? '1' : '0',
                    'shop_fee_rate_override' => $row['shop_fee_rate_override'] ?? '',
                    'default_parts_matrix_key' => $row['default_parts_matrix_key'] ?? '',
                ];

                if (! in_array($profile, ['fleet', 'warranty'], true)) {
                    $posted['discount_type'] = $row['discount_type'];
                    $posted['discount_amount'] = $row['discount_amount'] ?? '';
                }

                return array_merge($posted, $changes[$row['name']] ?? []);
            })
            ->all(),
    ];
}

function blankDiscountRepairOrder(string $billingClass): RepairOrderLine
{
    $repairOrder = repairOrderForFinancialAuthority();
    $repairOrder->customer->update(['customer_type' => $billingClass]);

    return $repairOrder->lines()->create([
        'repair_order_concern_id' => concernForFinancialAuthority($repairOrder)->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Brake labor',
        'quantity' => '1.00',
        'unit_price_cents' => 10000,
    ]);
}

function blankDiscountStoredRow(string $name): array
{
    return collect(ShopSettings::current()->customer_types)->firstWhere('name', $name);
}

function blankDiscountTotals(RepairOrderLine $line): array
{
    $line = $line->fresh();
    $totals = app(EstimateTotalsCalculator::class)->totalsFor(
        RepairOrder::query()->with(['customer', 'concerns', 'lines.concern'])->findOrFail($line->repair_order_id),
    );

    return [
        'line_discount' => $line->standing_discount_cents,
        'line_total' => $line->total_cents,
        'estimate_discount' => $totals->standingDiscountCents(),
        'estimate_total' => $totals->totalCents(),
    ];
}

test('the resolver treats a missing discount override as the built-in percent, which only Military has', function () {
    $settings = ShopSettings::current();

    expect(ShopSettings::builtInDiscountAmountFor('Military'))->toBe('10.00')
        ->and(ShopSettings::builtInDiscountAmountFor('Retail'))->toBeNull()
        ->and(ShopSettings::builtInDiscountAmountFor('Veteran'))->toBeNull()
        ->and($settings->normalizeCustomerTypeRow(['name' => 'Military', 'discount_type' => 'labor', 'discount_amount' => null])['discount_amount'])->toBe('10.00')
        ->and(ShopSettings::standingDiscountPolicyFromRow($settings->normalizeCustomerTypeRow(['name' => 'Veteran', 'discount_type' => 'labor', 'discount_amount' => null])))->toBeNull()
        ->and(ShopSettings::standingDiscountPolicyFromRow($settings->normalizeCustomerTypeRow(['name' => 'Retail', 'discount_type' => 'labor', 'discount_amount' => null])))->toBeNull();
});

test('a blank discount percent saves no override instead of keeping the previous value', function () {
    $line = blankDiscountRepairOrder('Veteran');
    app(EstimateTotalsCalculator::class)->recalculateLivingRepairOrders();

    expect(blankDiscountTotals($line)['line_discount'])->toBe(1500);

    $this->patch(route('operations.settings.shop.customer-types.update'), blankDiscountFormPayload([
        'Veteran' => ['discount_amount' => ''],
    ]))->assertRedirect();

    $repairOrder = RepairOrder::query()->findOrFail($line->repair_order_id);
    app(EstimateTotalsCalculator::class)->ensureFinancialTotalsAreCurrent($repairOrder->fresh(['lines', 'customer', 'concerns']));

    expect(blankDiscountStoredRow('Veteran')['discount_type'])->toBe('labor')
        ->and(blankDiscountStoredRow('Veteran')['discount_amount'])->toBeNull()
        ->and(ShopSettings::current()->standingDiscountPolicy('Veteran'))->toBeNull()
        // Forward-only (#181): existing RO keeps the stamped 15% commercial rules.
        ->and(blankDiscountTotals($line))->toBe([
            'line_discount' => 1500,
            'line_total' => 8500,
            'estimate_discount' => 1500,
            'estimate_total' => 8500,
        ]);

    $newer = blankDiscountRepairOrder('Veteran');
    app(EstimateTotalsCalculator::class)->ensureFinancialTotalsAreCurrent(
        RepairOrder::query()->with(['lines', 'customer', 'concerns'])->findOrFail($newer->repair_order_id),
    );
    expect(blankDiscountTotals($newer)['line_discount'])->toBe(0);
});

test('a typed zero saves for new ROs only; existing ROs keep stamped discounts', function () {
    $veteranLine = blankDiscountRepairOrder('Veteran');
    $militaryLine = blankDiscountRepairOrder('Military');
    app(EstimateTotalsCalculator::class)->recalculateLivingRepairOrders();

    $this->patch(route('operations.settings.shop.customer-types.update'), blankDiscountFormPayload([
        'Veteran' => ['discount_amount' => '0'],
        'Military' => ['discount_amount' => '0'],
    ]))->assertRedirect();

    app(EstimateTotalsCalculator::class)->recalculateLivingRepairOrders();

    expect(blankDiscountStoredRow('Veteran')['discount_amount'])->toBe('0.00')
        ->and(blankDiscountStoredRow('Military')['discount_amount'])->toBe('0.00')
        ->and(ShopSettings::current()->standingDiscountPolicy('Military'))->toBeNull()
        ->and(blankDiscountTotals($veteranLine)['line_discount'])->toBe(1500)
        ->and(blankDiscountTotals($militaryLine)['line_discount'])->toBe(1500);

    $newVeteran = blankDiscountRepairOrder('Veteran');
    app(EstimateTotalsCalculator::class)->ensureFinancialTotalsAreCurrent(
        RepairOrder::query()->with(['lines', 'customer', 'concerns'])->findOrFail($newVeteran->repair_order_id),
    );
    expect(blankDiscountTotals($newVeteran)['line_discount'])->toBe(0);
});

test('a typed discount percent saves for future ROs without repricing existing ones', function () {
    $line = blankDiscountRepairOrder('Veteran');
    app(EstimateTotalsCalculator::class)->recalculateLivingRepairOrders();
    expect(blankDiscountTotals($line)['line_discount'])->toBe(1500);

    $this->patch(route('operations.settings.shop.customer-types.update'), blankDiscountFormPayload([
        'Veteran' => ['discount_amount' => '12.5'],
    ]))->assertRedirect();

    app(EstimateTotalsCalculator::class)->recalculateLivingRepairOrders();

    expect(blankDiscountStoredRow('Veteran')['discount_amount'])->toBe('12.50')
        ->and(ShopSettings::current()->standingDiscountPolicy('Veteran'))->toBe(['type' => 'labor', 'percent' => '12.50'])
        ->and(blankDiscountTotals($line))->toBe([
            'line_discount' => 1500,
            'line_total' => 8500,
            'estimate_discount' => 1500,
            'estimate_total' => 8500,
        ]);

    $newer = blankDiscountRepairOrder('Veteran');
    app(EstimateTotalsCalculator::class)->ensureFinancialTotalsAreCurrent(
        RepairOrder::query()->with(['lines', 'customer', 'concerns'])->findOrFail($newer->repair_order_id),
    );
    expect(blankDiscountTotals($newer))->toBe([
        'line_discount' => 1250,
        'line_total' => 8750,
        'estimate_discount' => 1250,
        'estimate_total' => 8750,
    ]);
});

test('a blank Military discount saves no override and inherits the built-in 10 percent shown on the form', function () {
    $line = blankDiscountRepairOrder('Military');
    app(EstimateTotalsCalculator::class)->recalculateLivingRepairOrders();
    expect(blankDiscountTotals($line)['line_discount'])->toBe(1500); // stamped 15% from beforeEach

    $this->patch(route('operations.settings.shop.customer-types.update'), blankDiscountFormPayload([
        'Military' => ['discount_amount' => ''],
    ]))->assertRedirect();

    app(EstimateTotalsCalculator::class)->recalculateLivingRepairOrders();

    expect(blankDiscountStoredRow('Military')['discount_type'])->toBe('labor')
        ->and(blankDiscountStoredRow('Military')['discount_amount'])->toBeNull()
        ->and(ShopSettings::current()->standingDiscountPolicy('Military'))->toBe(['type' => 'labor', 'percent' => '10.00'])
        // Existing RO keeps stamped 15%; live settings now resolve built-in 10% for new ROs.
        ->and(blankDiscountTotals($line)['line_discount'])->toBe(1500);

    $newer = blankDiscountRepairOrder('Military');
    app(EstimateTotalsCalculator::class)->ensureFinancialTotalsAreCurrent(
        RepairOrder::query()->with(['lines', 'customer', 'concerns'])->findOrFail($newer->repair_order_id),
    );
    expect(blankDiscountTotals($newer)['line_discount'])->toBe(1000);

    // Class Settings / JSON API is the commercial editor after #180 tab retirement.
    // Built-in Military 10% is resolver behavior when the override is blank — not a retired tab placeholder.
    $catalogClasses = $this->getJson(route('operations.settings.shop.customer-types.show'))
        ->assertOk()
        ->json('classes');
    $militaryRow = collect($catalogClasses)->first(
        fn (array $row): bool => strcasecmp((string) ($row['name'] ?? ''), 'Military') === 0
            || ($row['key'] ?? null) === 'military',
    );
    $veteranRow = collect($catalogClasses)->firstWhere('name', 'Veteran');
    $militaryKey = (string) ($militaryRow['key'] ?? blankDiscountStoredRow('Military')['key'] ?? 'military');

    expect($militaryRow)->not->toBeNull()
        ->and($militaryRow['discount_type'] ?? null)->toBe('labor')
        // JSON may omit null overrides; storage still has an explicit null (asserted above).
        ->and($militaryRow['discount_amount'] ?? null)->toBeNull()
        ->and(ShopSettings::builtInDiscountAmountFor('Military'))->toBe('10.00')
        ->and($veteranRow['discount_amount'] ?? null)->toBe('15.00');

    $this->get(route('operations.settings.shop.edit', [
        'section' => 'financial',
        'financial-tab' => 'labor',
    ]))
        ->assertOk()
        ->assertSee('data-billing-classes-shared', false)
        ->assertSee('placeholder="No discount"', false)
        ->assertDontSee('Uses the built-in 10% when blank. Enter 0 for no discount.', false)
        ->assertDontSee('id="financial-panel-billing-classes"', false);

    // Class Settings item update with a blank amount keeps the same Military inheritance.
    $this->patchJson(route('operations.settings.shop.customer-types.item.update', ['classKey' => $militaryKey]), [
        'name' => 'Military',
        'pill_color' => $militaryRow['pill_color'] ?? 'slate',
        'document_presentation_profile' => $militaryRow['document_presentation_profile'] ?? 'retail',
        'shop_fees_enabled' => true,
        'shop_fee_rate_override' => null,
        'default_parts_matrix_key' => null,
        'discount_type' => 'labor',
        'discount_amount' => '',
        'concern_billing_posture' => null,
    ])
        ->assertOk();

    expect(blankDiscountStoredRow('Military')['discount_amount'])->toBeNull();

    expect(ShopSettings::current()->standingDiscountPolicy('Military'))->toBe(['type' => 'labor', 'percent' => '10.00'])
        ->and(blankDiscountTotals($line)['line_discount'])->toBe(1500);

    $afterBlankApi = blankDiscountRepairOrder('Military');
    app(EstimateTotalsCalculator::class)->ensureFinancialTotalsAreCurrent(
        RepairOrder::query()->with(['lines', 'customer', 'concerns'])->findOrFail($afterBlankApi->repair_order_id),
    );
    expect(blankDiscountTotals($afterBlankApi)['line_discount'])->toBe(1000);
});

test('an unrelated billing class save leaves every other class and its pricing untouched', function () {
    $this->patch(route('operations.settings.shop.customer-types.update'), blankDiscountFormPayload([
        'Military' => ['discount_amount' => ''],
    ]))->assertRedirect();

    $militaryLine = blankDiscountRepairOrder('Military');
    $veteranLine = blankDiscountRepairOrder('Veteran');
    app(EstimateTotalsCalculator::class)->recalculateLivingRepairOrders();
    $before = collect(ShopSettings::current()->customer_types)->keyBy('name');

    $this->patch(route('operations.settings.shop.customer-types.update'), blankDiscountFormPayload([
        'Retail' => ['pill_color' => 'blue'],
    ]))->assertRedirect();

    $after = collect(ShopSettings::current()->customer_types)->keyBy('name');

    expect($after['Retail']['pill_color'])->toBe('blue')
        ->and($after->except('Retail')->all())->toBe($before->except('Retail')->all())
        ->and($after['Military']['discount_amount'])->toBeNull()
        ->and($after['Fleet']['discount_amount'])->toBe('5.00')
        ->and(blankDiscountTotals($militaryLine)['line_discount'])->toBe(1000)
        ->and(blankDiscountTotals($veteranLine)['line_discount'])->toBe(1500);

    ShopSettings::current()->update([
        'customer_types' => ShopSettings::current()->customerTypesWithoutPartsMatrix('no-such-matrix'),
    ]);

    expect(collect(ShopSettings::current()->customer_types)->keyBy('name')->all())->toBe($after->all());
});
