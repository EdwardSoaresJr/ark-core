<?php

use App\Ark\Operations\EstimatePricing\ApplyBillingClassMinimumsAction;
use App\Ark\Operations\EstimatePricing\LaborBillingMinimumMode;
use App\Ark\Operations\EstimatePricing\LaborBillingMinimums;
use App\Ark\Operations\EstimatePricing\LaborPricingProfile;
use App\Ark\Operations\RepairOrders\ConcernBillingPosture;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\Settings\ShopSettings;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);

    $categories = collect(ShopSettings::DEFAULT_LABOR_CATEGORIES)
        ->map(function (array $category): array {
            $category['minimum_hours'] = '0.00';

            return $category;
        })
        ->all();

    ShopSettings::current()->update([
        'labor_categories' => $categories,
        'default_labor_rate_cents' => 16500,
        'labor_pricing_profile_version' => 1,
        'labor_billing_minimums' => [
            'customer_pay' => [
                'mode' => LaborBillingMinimumMode::PerRepairOrder->value,
                'hours' => '1.00',
                'shortfall_rate_cents' => 16500,
                'shortfall_rate_source' => LaborBillingMinimums::SHORTFALL_STANDARD_MECHANICAL,
            ],
            'warranty_other' => [
                'mode' => LaborBillingMinimumMode::None->value,
                'hours' => '0.00',
                'shortfall_rate_cents' => 16500,
                'shortfall_rate_source' => LaborBillingMinimums::SHORTFALL_STANDARD_MECHANICAL,
            ],
        ],
    ]);
});

function stampRetailRoMinimumProfile($repairOrder, string $mode = 'per_ro', string $hours = '1.00'): void
{
    $repairOrder->forceFill([
        'labor_pricing_profile' => (new LaborPricingProfile(
            version: 1,
            billingMinimums: [
                'customer_pay' => [
                    'mode' => $mode,
                    'hours' => $hours,
                    'shortfall_rate_cents' => 16500,
                    'shortfall_rate_source' => LaborBillingMinimums::SHORTFALL_STANDARD_MECHANICAL,
                ],
                'warranty_other' => [
                    'mode' => LaborBillingMinimumMode::None->value,
                    'hours' => '0.00',
                    'shortfall_rate_cents' => 16500,
                    'shortfall_rate_source' => LaborBillingMinimums::SHORTFALL_STANDARD_MECHANICAL,
                ],
            ],
            defaultLaborRateCents: 16500,
            schema: 1,
        ))->toArray(),
    ])->save();
}

function createLaborLine($repairOrder, RepairOrderConcern $concern, string $hours, int $rateCents = 16500): RepairOrderLine
{
    return RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => $concern->summary.' labor',
        'quantity' => $hours,
        'labor_entered_hours' => $hours,
        'labor_billed_hours' => $hours,
        'labor_rate_cents' => $rateCents,
        'unit_price_cents' => $rateCents,
        'subtotal_cents' => (int) round(((float) $hours) * $rateCents),
        'tax_cents' => 0,
        'shop_fee_cents' => 0,
        'standing_discount_cents' => 0,
        'total_cents' => (int) round(((float) $hours) * $rateCents),
    ]);
}

test('A: one Retail concern under RO minimum gets shortfall fee without mutating original hours', function () {
    $repairOrder = repairOrderForFinancialAuthority();
    stampRetailRoMinimumProfile($repairOrder);
    $concern = concernForFinancialAuthority($repairOrder);
    $concern->update([
        'summary' => 'Bulb repair',
        'billing_posture' => ConcernBillingPosture::CustomerPay,
    ]);

    $labor = createLaborLine($repairOrder, $concern, '0.40');

    $notices = app(ApplyBillingClassMinimumsAction::class)->synchronize($repairOrder->fresh(['concerns.lines']));

    $fee = RepairOrderLine::query()
        ->where('repair_order_id', $repairOrder->id)
        ->where('type', RepairOrderLineType::Fee)
        ->where('description', 'Billing minimum · Retail')
        ->first();

    expect($fee)->not->toBeNull()
        ->and((float) $fee->quantity)->toBe(0.60)
        ->and((int) $fee->unit_price_cents)->toBe(16500)
        ->and((int) $fee->subtotal_cents)->toBe(9900)
        ->and((float) $labor->fresh()->labor_billed_hours)->toBe(0.40)
        ->and((float) $labor->fresh()->quantity)->toBe(0.40)
        ->and($notices[0]['to_hours'] ?? null)->toBe('0.60');
});

test('B: second Retail concern clears RO-level minimum adjustment', function () {
    $repairOrder = repairOrderForFinancialAuthority();
    stampRetailRoMinimumProfile($repairOrder);
    $c1 = concernForFinancialAuthority($repairOrder);
    $c1->update(['summary' => 'Bulb repair', 'billing_posture' => ConcernBillingPosture::CustomerPay]);
    createLaborLine($repairOrder, $c1, '0.40');

    app(ApplyBillingClassMinimumsAction::class)->synchronize($repairOrder->fresh(['concerns.lines']));

    expect(RepairOrderLine::query()
        ->where('repair_order_id', $repairOrder->id)
        ->where('description', 'Billing minimum · Retail')
        ->exists())->toBeTrue();

    $c2 = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Air filter',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'billing_posture' => ConcernBillingPosture::CustomerPay,
        'recommendation_intent' => 'maintenance',
        'position' => 2,
    ]);
    createLaborLine($repairOrder, $c2, '0.60');

    $notices = app(ApplyBillingClassMinimumsAction::class)->synchronize($repairOrder->fresh(['concerns.lines']));

    expect(RepairOrderLine::query()
        ->where('repair_order_id', $repairOrder->id)
        ->where('description', 'Billing minimum · Retail')
        ->exists())->toBeFalse()
        ->and($notices[0]['from_hours'] ?? null)->toBe('0.60')
        ->and($notices[0]['to_hours'] ?? null)->toBe('0.00');
});

test('C: Warranty hours do not satisfy Retail RO minimum', function () {
    $repairOrder = repairOrderForFinancialAuthority();
    stampRetailRoMinimumProfile($repairOrder);

    $retail = concernForFinancialAuthority($repairOrder);
    $retail->update(['summary' => 'Bulb repair', 'billing_posture' => ConcernBillingPosture::CustomerPay]);
    createLaborLine($repairOrder, $retail, '0.40');

    $warranty = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Warranty repair',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'billing_posture' => ConcernBillingPosture::WarrantyOther,
        'recommendation_intent' => 'maintenance',
        'position' => 2,
    ]);
    createLaborLine($repairOrder, $warranty, '2.00', 15000);

    app(ApplyBillingClassMinimumsAction::class)->synchronize($repairOrder->fresh(['concerns.lines']));

    $fee = RepairOrderLine::query()
        ->where('repair_order_id', $repairOrder->id)
        ->where('description', 'Billing minimum · Retail')
        ->first();

    expect($fee)->not->toBeNull()
        ->and((float) $fee->quantity)->toBe(0.60)
        ->and((int) $fee->subtotal_cents)->toBe(9900);
});

test('D: per-line mode applies a shortfall per eligible labor line', function () {
    $repairOrder = repairOrderForFinancialAuthority();
    stampRetailRoMinimumProfile($repairOrder, LaborBillingMinimumMode::PerLine->value, '1.00');

    $concern = concernForFinancialAuthority($repairOrder);
    $concern->update(['billing_posture' => ConcernBillingPosture::CustomerPay]);
    $lineA = createLaborLine($repairOrder, $concern, '0.40');
    $lineB = createLaborLine($repairOrder, $concern, '0.60');

    app(ApplyBillingClassMinimumsAction::class)->synchronize($repairOrder->fresh(['concerns.lines']));

    $fees = RepairOrderLine::query()
        ->where('repair_order_id', $repairOrder->id)
        ->where('type', RepairOrderLineType::Fee)
        ->where('description', 'like', 'Billing minimum · Retail · line %')
        ->orderBy('id')
        ->get();

    expect($fees)->toHaveCount(2)
        ->and((float) $fees[0]->quantity)->toBe(0.60)
        ->and((float) $fees[1]->quantity)->toBe(0.40)
        ->and((float) $lineA->fresh()->labor_billed_hours)->toBe(0.40)
        ->and((float) $lineB->fresh()->labor_billed_hours)->toBe(0.60);
});

test('legacy null pricing profile never applies billing-class minimums', function () {
    $repairOrder = repairOrderForFinancialAuthority();
    $repairOrder->forceFill(['labor_pricing_profile' => null])->save();

    $concern = concernForFinancialAuthority($repairOrder);
    $concern->update(['billing_posture' => ConcernBillingPosture::CustomerPay]);
    createLaborLine($repairOrder, $concern, '0.40');

    // Shop now has active minimums, but this pre-activation RO must stay legacy.
    ShopSettings::current()->update([
        'labor_pricing_profile_version' => 1,
        'labor_billing_minimums' => [
            'customer_pay' => [
                'mode' => LaborBillingMinimumMode::PerRepairOrder->value,
                'hours' => '1.00',
                'shortfall_rate_cents' => 16500,
                'shortfall_rate_source' => LaborBillingMinimums::SHORTFALL_STANDARD_MECHANICAL,
            ],
        ],
    ]);

    app(ApplyBillingClassMinimumsAction::class)->synchronize($repairOrder->fresh(['concerns.lines']));

    expect(RepairOrderLine::query()
        ->where('repair_order_id', $repairOrder->id)
        ->where('description', 'like', 'Billing minimum · %')
        ->exists())->toBeFalse();
});

test('new RO created after activation receives stamped profile', function () {
    ShopSettings::current()->update([
        'labor_pricing_profile_version' => 1,
        'labor_billing_minimums' => [
            'customer_pay' => [
                'mode' => LaborBillingMinimumMode::PerRepairOrder->value,
                'hours' => '1.00',
                'shortfall_rate_cents' => 16500,
                'shortfall_rate_source' => LaborBillingMinimums::SHORTFALL_STANDARD_MECHANICAL,
            ],
        ],
    ]);

    $repairOrder = repairOrderForFinancialAuthority();

    expect($repairOrder->labor_pricing_profile)->toBeArray()
        ->and($repairOrder->labor_pricing_profile['version'])->toBe(1)
        ->and($repairOrder->labor_pricing_profile['billing_minimums']['customer_pay']['mode'])->toBe('per_ro');
});
