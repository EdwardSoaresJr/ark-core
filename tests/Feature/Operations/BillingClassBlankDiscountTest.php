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

    expect(blankDiscountStoredRow('Veteran')['discount_type'])->toBe('labor')
        ->and(blankDiscountStoredRow('Veteran')['discount_amount'])->toBeNull()
        ->and(ShopSettings::current()->standingDiscountPolicy('Veteran'))->toBeNull()
        ->and(blankDiscountTotals($line))->toBe([
            'line_discount' => 0,
            'line_total' => 10000,
            'estimate_discount' => 0,
            'estimate_total' => 10000,
        ]);
});

test('a typed zero saves zero and applies no discount, even on Military', function () {
    $veteranLine = blankDiscountRepairOrder('Veteran');
    $militaryLine = blankDiscountRepairOrder('Military');

    $this->patch(route('operations.settings.shop.customer-types.update'), blankDiscountFormPayload([
        'Veteran' => ['discount_amount' => '0'],
        'Military' => ['discount_amount' => '0'],
    ]))->assertRedirect();

    expect(blankDiscountStoredRow('Veteran')['discount_amount'])->toBe('0.00')
        ->and(blankDiscountStoredRow('Military')['discount_amount'])->toBe('0.00')
        ->and(ShopSettings::current()->standingDiscountPolicy('Military'))->toBeNull()
        ->and(blankDiscountTotals($veteranLine)['line_discount'])->toBe(0)
        ->and(blankDiscountTotals($veteranLine)['estimate_total'])->toBe(10000)
        ->and(blankDiscountTotals($militaryLine)['line_discount'])->toBe(0)
        ->and(blankDiscountTotals($militaryLine)['estimate_total'])->toBe(10000);
});

test('a typed discount percent saves and prices at that percent', function () {
    $line = blankDiscountRepairOrder('Veteran');

    $this->patch(route('operations.settings.shop.customer-types.update'), blankDiscountFormPayload([
        'Veteran' => ['discount_amount' => '12.5'],
    ]))->assertRedirect();

    expect(blankDiscountStoredRow('Veteran')['discount_amount'])->toBe('12.50')
        ->and(ShopSettings::current()->standingDiscountPolicy('Veteran'))->toBe(['type' => 'labor', 'percent' => '12.50'])
        ->and(blankDiscountTotals($line))->toBe([
            'line_discount' => 1250,
            'line_total' => 8750,
            'estimate_discount' => 1250,
            'estimate_total' => 8750,
        ]);
});

test('a blank Military discount saves no override and inherits the built-in 10 percent shown on the form', function () {
    $line = blankDiscountRepairOrder('Military');

    $this->patch(route('operations.settings.shop.customer-types.update'), blankDiscountFormPayload([
        'Military' => ['discount_amount' => ''],
    ]))->assertRedirect();

    expect(blankDiscountStoredRow('Military')['discount_type'])->toBe('labor')
        ->and(blankDiscountStoredRow('Military')['discount_amount'])->toBeNull()
        ->and(ShopSettings::current()->standingDiscountPolicy('Military'))->toBe(['type' => 'labor', 'percent' => '10.00'])
        ->and(blankDiscountTotals($line))->toBe([
            'line_discount' => 1000,
            'line_total' => 9000,
            'estimate_discount' => 1000,
            'estimate_total' => 9000,
        ]);

    $html = $this->get(route('operations.settings.shop.edit', ['section' => 'financial']))
        ->assertOk()
        ->assertSee('Uses the built-in 10% when blank. Enter 0 for no discount.')
        ->assertSee('No discount when blank.')
        ->getContent();

    expect($html)->toMatch('/name="customer_types\[3\]\[discount_amount\]"\s+value=""\s+inputmode="decimal"\s+placeholder="Built-in 10%"/')
        ->and($html)->toMatch('/name="customer_types\[4\]\[discount_amount\]"\s+value="15.00"\s+inputmode="decimal"\s+placeholder="No discount"/');
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
