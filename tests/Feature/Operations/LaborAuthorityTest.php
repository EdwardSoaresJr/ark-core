<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Documents\EstimateSnapshotBuilder;
use App\Ark\Operations\Labor\LaborAdjustment;
use App\Ark\Operations\Labor\LaborAdjustmentReason;
use App\Ark\Operations\Labor\LaborAuthority;
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
    ShopSettings::current()->update([
        'labor_categories' => ShopSettings::DEFAULT_LABOR_CATEGORIES,
        'default_labor_rate_cents' => 16500,
    ]);
});

test('labor authority applies category minimum and difficult adjustment', function () {
    $resolved = app(LaborAuthority::class)->resolveForLine([
        'labor_category_key' => 'mechanical',
        'labor_entered_hours' => '1.50',
        'labor_adjustment' => LaborAdjustment::Difficult->value,
        'labor_adjustment_reason' => LaborAdjustmentReason::Corrosion->value,
    ], ShopSettings::current());

    expect($resolved)
        ->labor_entered_hours->toBe('1.50')
        ->labor_billed_hours->toBe('2.00')
        ->quantity->toBe('2.00')
        ->labor_adjustment->toBe('difficult')
        ->labor_adjustment_factor->toBe('1.25')
        ->labor_adjustment_reason->toBe('Corrosion')
        ->labor_rate_cents->toBe(16500)
        ->resolved_from_posture->toBe('customer_pay')
        ->resolved_from_operation_class->toBe('general_repair')
        ->labor_policy_id->not->toBeNull()
        ->labor_policy_version->toBe(1);
});

test('labor authority maps diagnostic category to diagnostics operation class', function () {
    $resolved = app(LaborAuthority::class)->resolveForLine([
        'labor_category_key' => 'diagnostic',
        'labor_entered_hours' => '1.00',
        'labor_adjustment' => LaborAdjustment::Normal->value,
    ], ShopSettings::current());

    expect($resolved)
        ->resolved_from_operation_class->toBe('diagnostics')
        ->resolved_from_posture->toBe('customer_pay')
        ->labor_rate_cents->toBe(16500);
});

test('courtesy category applies zero rate unless labor rate is explicitly overridden', function () {
    $resolved = app(LaborAuthority::class)->resolveForLine([
        'labor_category_key' => 'courtesy',
        'labor_entered_hours' => '1.00',
        'labor_adjustment' => LaborAdjustment::Normal->value,
        'unit_price' => '165.00',
    ], ShopSettings::current());

    expect($resolved)
        ->labor_category_key->toBe('courtesy')
        ->unit_price_cents->toBe(0)
        ->labor_rate_cents->toBe(0);
});

test('courtesy category keeps an explicit labor rate override', function () {
    $resolved = app(LaborAuthority::class)->resolveForLine([
        'labor_category_key' => 'courtesy',
        'labor_entered_hours' => '1.00',
        'labor_adjustment' => LaborAdjustment::Normal->value,
        'unit_price' => '75.00',
        'labor_rate_overridden' => '1',
        'labor_rate_override_reason' => \App\Ark\Operations\EstimatePricing\LaborRateOverrideReason::CustomerGoodwill->value,
    ], ShopSettings::current());

    expect($resolved)
        ->labor_category_key->toBe('courtesy')
        ->unit_price_cents->toBe(7500)
        ->labor_rate_cents->toBe(7500)
        ->policy_resolved_labor_rate_cents->toBe(0)
        ->labor_rate_override_reason->toBe('customer_goodwill');
});

test('labor rate override requires a policy reason', function () {
    app(LaborAuthority::class)->resolveForLine([
        'labor_category_key' => 'mechanical',
        'labor_entered_hours' => '1.00',
        'labor_adjustment' => LaborAdjustment::Normal->value,
        'unit_price' => '99.00',
        'labor_rate_overridden' => '1',
    ], ShopSettings::current());
})->throws(\Illuminate\Validation\ValidationException::class);

test('worksheet stores a custom labor rate when override reason is provided', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    [$repairOrder, $concern] = repairOrderForLaborAuthority();

    $this->actingAs($advisor)->post(route('operations.repair-orders.lines.store', $repairOrder), [
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor->value,
        'description' => 'Custom-rate diagnosis',
        'labor_category_key' => 'mechanical',
        'labor_entered_hours' => '1.00',
        'labor_adjustment' => LaborAdjustment::Normal->value,
        'unit_price' => '99.00',
        'labor_rate_overridden' => '1',
    ])->assertSessionHasErrors('labor_rate_override_reason');

    $this->actingAs($advisor)->post(route('operations.repair-orders.lines.store', $repairOrder), [
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor->value,
        'description' => 'Custom-rate diagnosis',
        'labor_category_key' => 'mechanical',
        'labor_entered_hours' => '1.00',
        'labor_adjustment' => LaborAdjustment::Normal->value,
        'unit_price' => '99.00',
        'labor_rate_overridden' => '1',
        'labor_rate_override_reason' => \App\Ark\Operations\EstimatePricing\LaborRateOverrideReason::CompetitiveMatch->value,
    ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $line = RepairOrderLine::query()->where('repair_order_id', $repairOrder->id)->sole();

    expect($line)
        ->unit_price_cents->toBe(9900)
        ->labor_rate_cents->toBe(9900)
        ->policy_resolved_labor_rate_cents->toBe(16500)
        ->labor_rate_override_reason->toBe('competitive_match');

    $this->actingAs($advisor)
        ->get(route('operations.repair-orders.show', [
            'repairOrder' => $repairOrder,
            'editing_line' => $line->id,
        ]))
        ->assertOk()
        ->assertSee('Rate override reason', false)
        ->assertSee('name="labor_rate_override_reason"', false)
        ->assertSee('value="competitive_match"', false);
});

test('repairpal category ignores difficulty adjustment hours override and rate override', function () {
    $resolved = app(LaborAuthority::class)->resolveForLine([
        'labor_category_key' => ShopSettings::WARRANTY_REPAIRPAL_LABOR_CATEGORY_KEY,
        'labor_entered_hours' => '2.00',
        'labor_adjustment' => LaborAdjustment::Difficult->value,
        'labor_adjustment_reason' => LaborAdjustmentReason::Corrosion->value,
        'unit_price' => '175.00',
        'labor_rate_overridden' => '1',
        'quantity' => '3.50',
        'labor_hours_overridden' => '1',
        'labor_override_reason' => 'Should be ignored.',
    ], ShopSettings::current());

    expect($resolved)
        ->labor_category_key->toBe('repairpal')
        ->labor_adjustment->toBe('normal')
        ->labor_adjustment_factor->toBe('1.00')
        ->labor_adjustment_reason->toBeNull()
        ->labor_billed_hours->toBe('2.00')
        ->quantity->toBe('2.00')
        ->labor_hours_overridden->toBeFalse()
        ->labor_override_reason->toBeNull()
        ->unit_price_cents->toBe(15000)
        ->labor_rate_cents->toBe(15000);
});

test('warranty other category ignores difficulty adjustment hours override and rate override', function () {
    $resolved = app(LaborAuthority::class)->resolveForLine([
        'labor_category_key' => ShopSettings::WARRANTY_OTHER_LABOR_CATEGORY_KEY,
        'labor_entered_hours' => '1.50',
        'labor_adjustment' => LaborAdjustment::Severe->value,
        'labor_adjustment_reason' => LaborAdjustmentReason::AccessDifficulty->value,
        'unit_price' => '200.00',
        'labor_rate_overridden' => '1',
        'quantity' => '3.00',
        'labor_hours_overridden' => '1',
        'labor_override_reason' => 'Should be ignored.',
    ], ShopSettings::current());

    expect($resolved)
        ->labor_category_key->toBe('warranty-other')
        ->labor_adjustment->toBe('normal')
        ->labor_billed_hours->toBe('1.50')
        ->quantity->toBe('1.50')
        ->labor_hours_overridden->toBeFalse()
        ->unit_price_cents->toBe(16500);
});

test('worksheet strips repairpal labor modifiers on store', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    [$repairOrder, $concern] = repairOrderForLaborAuthority();

    $this->actingAs($advisor)->post(route('operations.repair-orders.lines.store', $repairOrder), [
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor->value,
        'description' => 'RepairPal water pump',
        'labor_category_key' => ShopSettings::WARRANTY_REPAIRPAL_LABOR_CATEGORY_KEY,
        'labor_entered_hours' => '2.00',
        'labor_adjustment' => LaborAdjustment::Difficult->value,
        'labor_adjustment_reason' => LaborAdjustmentReason::Corrosion->value,
        'unit_price' => '175.00',
        'labor_rate_overridden' => '1',
        'quantity' => '3.50',
        'labor_hours_overridden' => '1',
        'labor_override_reason' => 'Should be ignored.',
    ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $line = RepairOrderLine::query()->where('repair_order_id', $repairOrder->id)->sole();

    expect($line)
        ->labor_category_key->toBe('repairpal')
        ->labor_adjustment->toBe('normal')
        ->quantity->toBe('2.00')
        ->labor_hours_overridden->toBeFalse()
        ->unit_price_cents->toBe(15000);
});

test('repairpal allows modifiers when enabled in shop settings', function () {
    ShopSettings::current()->update([
        'labor_categories' => collect(ShopSettings::DEFAULT_LABOR_CATEGORIES)
            ->map(function (array $category): array {
                if ($category['key'] === ShopSettings::WARRANTY_REPAIRPAL_LABOR_CATEGORY_KEY) {
                    $category['allows_modifiers'] = true;
                }

                return $category;
            })
            ->all(),
    ]);

    $resolved = app(LaborAuthority::class)->resolveForLine([
        'labor_category_key' => ShopSettings::WARRANTY_REPAIRPAL_LABOR_CATEGORY_KEY,
        'labor_entered_hours' => '2.00',
        'labor_adjustment' => LaborAdjustment::Difficult->value,
        'labor_adjustment_reason' => LaborAdjustmentReason::Corrosion->value,
        'unit_price' => '150.00',
        'labor_rate_overridden' => '0',
    ], ShopSettings::current());

    expect($resolved)
        ->labor_adjustment->toBe('difficult')
        ->labor_billed_hours->toBe('2.50')
        ->quantity->toBe('2.50');
});

test('exact rounding preserves book hours after minimum and adjustment', function () {
    $categories = collect(ShopSettings::DEFAULT_LABOR_CATEGORIES)
        ->map(fn (array $category): array => $category['key'] === 'mechanical'
            ? array_merge($category, ['rounding_rule' => 'exact'])
            : $category)
        ->all();

    ShopSettings::current()->update([
        'labor_categories' => $categories,
    ]);

    $resolved = app(LaborAuthority::class)->resolveForLine([
        'labor_category_key' => 'mechanical',
        'labor_entered_hours' => '1.37',
        'labor_adjustment' => LaborAdjustment::Normal->value,
    ], ShopSettings::current());

    expect($resolved)
        ->labor_billed_hours->toBe('1.37')
        ->quantity->toBe('1.37');
});

test('quarter rounding rounds up book hours below the next increment', function () {
    $resolved = app(LaborAuthority::class)->resolveForLine([
        'labor_category_key' => 'mechanical',
        'labor_entered_hours' => '1.10',
        'labor_adjustment' => LaborAdjustment::Normal->value,
    ], ShopSettings::current());

    expect($resolved)
        ->labor_billed_hours->toBe('1.25')
        ->quantity->toBe('1.25');
});

test('diagnostic category enforces one hour minimum before rounding', function () {
    $resolved = app(LaborAuthority::class)->resolveForLine([
        'labor_category_key' => 'diagnostic',
        'labor_entered_hours' => '0.50',
        'labor_adjustment' => LaborAdjustment::Normal->value,
        'quantity' => '1.00',
    ], ShopSettings::current());

    expect($resolved)
        ->labor_billed_hours->toBe('1.00')
        ->quantity->toBe('1.00')
        ->labor_minimum_applied->toBeTrue();
});

test('labor override requires a reason when final hours differ from calculated', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    [$repairOrder, $concern] = repairOrderForLaborAuthority();

    $this->actingAs($advisor)->post(route('operations.repair-orders.lines.store', $repairOrder), [
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor->value,
        'description' => 'Timing cover reseal',
        'labor_category_key' => 'mechanical',
        'labor_entered_hours' => '3.00',
        'labor_adjustment' => LaborAdjustment::Difficult->value,
        'labor_adjustment_reason' => LaborAdjustmentReason::Corrosion->value,
        'unit_price' => '165.00',
        'quantity' => '4.50',
        'labor_hours_overridden' => '1',
    ])->assertSessionHasErrors('labor_override_reason');

    $this->actingAs($advisor)->post(route('operations.repair-orders.lines.store', $repairOrder), [
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor->value,
        'description' => 'Timing cover reseal',
        'labor_category_key' => 'mechanical',
        'labor_entered_hours' => '3.00',
        'labor_adjustment' => LaborAdjustment::Difficult->value,
        'labor_adjustment_reason' => LaborAdjustmentReason::Corrosion->value,
        'unit_price' => '165.00',
        'quantity' => '4.50',
        'labor_hours_overridden' => '1',
        'labor_override_reason' => 'Customer requested cap for this visit.',
    ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $line = RepairOrderLine::query()->where('repair_order_id', $repairOrder->id)->sole();

    expect($line)
        ->labor_hours_overridden->toBeTrue()
        ->quantity->toBe('4.50')
        ->labor_override_reason->toBe('Customer requested cap for this visit.');
});

test('difficult labor requires a reason when stored through the worksheet', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    [$repairOrder, $concern] = repairOrderForLaborAuthority();

    $this->actingAs($advisor)->post(route('operations.repair-orders.lines.store', $repairOrder), [
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor->value,
        'description' => 'Brake hose replacement',
        'labor_category_key' => 'mechanical',
        'labor_entered_hours' => '1.50',
        'labor_adjustment' => LaborAdjustment::Difficult->value,
        'unit_price' => '165.00',
        'quantity' => '1.88',
    ])->assertSessionHasErrors('labor_adjustment_reason');

    $this->actingAs($advisor)->post(route('operations.repair-orders.lines.store', $repairOrder), [
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor->value,
        'description' => 'Brake hose replacement',
        'labor_category_key' => 'mechanical',
        'labor_entered_hours' => '1.50',
        'labor_adjustment' => LaborAdjustment::Difficult->value,
        'labor_adjustment_reason' => LaborAdjustmentReason::AccessDifficulty->value,
        'unit_price' => '165.00',
    ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $line = RepairOrderLine::query()->where('repair_order_id', $repairOrder->id)->sole();

    expect($line)
        ->labor_category_key->toBe('mechanical')
        ->labor_entered_hours->toBe('1.50')
        ->labor_billed_hours->toBe('2.00')
        ->quantity->toBe('2.00')
        ->labor_adjustment_reason->toBe('Access Difficulty');
});

test('worksheet stores courtesy labor at zero rate and shows category on refresh', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    [$repairOrder, $concern] = repairOrderForLaborAuthority();

    $this->actingAs($advisor)->post(route('operations.repair-orders.lines.store', $repairOrder), [
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor->value,
        'description' => 'Courtesy diagnostic follow-up',
        'labor_category_key' => 'courtesy',
        'labor_entered_hours' => '1.00',
        'labor_adjustment' => LaborAdjustment::Normal->value,
        'unit_price' => '165.00',
        'labor_rate_overridden' => '0',
    ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $line = RepairOrderLine::query()->where('repair_order_id', $repairOrder->id)->sole();

    expect($line)
        ->labor_category_key->toBe('courtesy')
        ->labor_category_name->toBe('Courtesy')
        ->unit_price_cents->toBe(0)
        ->total_cents->toBe(0);

    $this->actingAs($advisor)
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Courtesy', false)
        ->assertSee('Courtesy diagnostic follow-up', false);
});

test('inline labor edit reopens with the saved labor category selected', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    [$repairOrder, $concern] = repairOrderForLaborAuthority();

    $this->actingAs($advisor)->post(route('operations.repair-orders.lines.store', $repairOrder), [
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor->value,
        'description' => 'Water pump labor',
        'labor_category_key' => 'mechanical',
        'labor_entered_hours' => '2.00',
        'labor_adjustment' => LaborAdjustment::Normal->value,
        'unit_price' => '165.00',
        'labor_rate_overridden' => '0',
    ])->assertRedirect();

    $line = RepairOrderLine::query()->where('repair_order_id', $repairOrder->id)->sole();

    $this->actingAs($advisor)
        ->get(route('operations.repair-orders.show', [
            'repairOrder' => $repairOrder,
            'editing_line' => $line->id,
        ]))
        ->assertOk()
        ->assertSee("laborCategoryKey: 'mechanical'", false)
        ->assertSee('value="mechanical"', false)
        ->assertSee('>Mechanical</option>', false);
});

test('estimate snapshot freezes labor authority fields', function () {
    [$repairOrder] = repairOrderForLaborAuthority();

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $repairOrder->concerns->first()->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Timing cover reseal',
        'quantity' => '2.50',
        'unit_price_cents' => 16500,
        'subtotal_cents' => 41250,
        'total_cents' => 41250,
        'labor_category_key' => 'mechanical',
        'labor_category_name' => 'Mechanical',
        'labor_entered_hours' => '1.80',
        'labor_adjustment' => 'difficult',
        'labor_adjustment_factor' => '1.25',
        'labor_adjustment_reason' => 'Corrosion',
        'labor_billed_hours' => '2.25',
        'labor_hours_overridden' => true,
        'labor_rate_cents' => 16500,
    ]);

    $snapshot = app(EstimateSnapshotBuilder::class)->build($repairOrder->fresh(['concerns.lines']));
    $line = $snapshot['concerns'][0]['lines'][0];

    expect($line)
        ->labor_entered_hours->toBe('1.80')
        ->labor_billed_hours->toBe('2.25')
        ->labor_adjustment_reason->toBe('Corrosion')
        ->labor_hours_overridden->toBeTrue();
});

/**
 * @return array{0: RepairOrder, 1: RepairOrderConcern}
 */
function repairOrderForLaborAuthority(): array
{
    $customer = Customer::query()->create([
        'first_name' => 'Jordan',
        'last_name' => 'Lee',
        'phone' => '555-0199',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2014,
        'make' => 'Subaru',
        'model' => 'Outback',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Oil leak',
    ]);

    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Oil leak',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'position' => 1,
    ]);

    return [$repairOrder->fresh(['customer', 'vehicle', 'concerns.lines']), $concern];
}
