<?php

use App\Ark\Operations\EstimatePricing\LaborPoliciesMatrixProjection;
use App\Ark\Operations\EstimatePricing\LaborPolicyResolver;
use App\Ark\Operations\EstimatePricing\OperationClass;
use App\Ark\Operations\EstimatePricing\UpsertLaborPolicyAction;
use App\Ark\Operations\Labor\LaborAdjustment;
use App\Ark\Operations\Labor\LaborAuthority;
use App\Ark\Operations\OperationAuthority\Operation;
use App\Ark\Operations\RepairOrders\ConcernBillingPosture;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLinePricing;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\StoreRepairOrderLine;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    ShopSettings::current()->update([
        'labor_categories' => ShopSettings::DEFAULT_LABOR_CATEGORIES,
        'default_labor_rate_cents' => 16500,
    ]);
});

test('every default labor category code has an active operation', function () {
    foreach (ShopSettings::DEFAULT_LABOR_CATEGORIES as $category) {
        $operation = Operation::query()
            ->where('code', $category['key'])
            ->where('is_active', true)
            ->first();

        expect($operation)->not->toBeNull("Missing Operation for labor category [{$category['key']}]");
        expect($operation->operationClassKey())->not->toBeEmpty();
    }
});

test('every matrix posture and operation class resolves today', function () {
    $resolver = app(LaborPolicyResolver::class);

    foreach (LaborPoliciesMatrixProjection::matrixPostures() as $posture) {
        foreach (OperationClass::query()->pluck('key') as $classKey) {
            $resolved = $resolver->resolve($posture, $classKey);

            expect($resolved->operationClassKey)->toBe($classKey)
                ->and($resolved->billingPosture)->toBe($posture->value)
                ->and($resolved->laborPolicyId)->not->toBeNull();
        }
    }
});

test('unknown operation code does not silently fall back to mechanical', function () {
    expect(fn () => Operation::forLine(null, 'not-a-real-operation'))
        ->toThrow(RuntimeException::class);
});

test('missing shop default operation configuration fails loudly', function () {
    Operation::query()->where('code', 'mechanical')->update(['is_active' => false]);

    expect(fn () => Operation::forLine(null, null))
        ->toThrow(RuntimeException::class, 'has no active Operation');
});

test('storing a labor line persists operation_id and pricing snapshot evidence', function () {
    $actor = User::factory()->create();
    $actor->assignRole(ArkRole::Advisor->value);

    $repairOrder = repairOrderForFinancialAuthority();
    $concern = concernForFinancialAuthority($repairOrder);

    $line = app(StoreRepairOrderLine::class)->store($repairOrder, [
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor->value,
        'description' => 'Front brakes',
        'labor_category_key' => 'diagnostic',
        'labor_entered_hours' => '1.00',
        'labor_adjustment' => LaborAdjustment::Normal->value,
    ], $actor);

    expect($line->operation_id)->not->toBeNull()
        ->and($line->labor_rate_cents)->toBe(16500)
        ->and($line->policy_resolved_labor_rate_cents)->toBe(16500)
        ->and($line->resolved_from_posture)->not->toBeNull()
        ->and($line->resolved_from_operation_class)->toBe('diagnostics')
        ->and($line->labor_policy_id)->not->toBeNull()
        ->and($line->labor_policy_version)->not->toBeNull();

    expect(Operation::query()->findOrFail($line->operation_id)->code)->toBe('diagnostic');
});

test('non-reprice edit preserves operation_id with pricing snapshot', function () {
    $repairOrder = repairOrderForFinancialAuthority();
    $concern = concernForFinancialAuthority($repairOrder);
    $mechanical = Operation::query()->where('code', 'mechanical')->firstOrFail();

    $created = app(RepairOrderLinePricing::class)->attributesFor([
        'type' => RepairOrderLineType::Labor->value,
        'repair_order_concern_id' => $concern->id,
        'labor_category_key' => 'mechanical',
        'labor_entered_hours' => '1.00',
        'labor_adjustment' => LaborAdjustment::Normal->value,
    ], $repairOrder);

    $line = RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Brake pads',
        'quantity' => $created['quantity'],
        'unit_price_cents' => $created['unit_price_cents'],
        'labor_category_key' => $created['labor_category_key'],
        'labor_category_name' => $created['labor_category_name'],
        'labor_entered_hours' => $created['labor_entered_hours'],
        'labor_adjustment' => $created['labor_adjustment'],
        'labor_adjustment_factor' => $created['labor_adjustment_factor'],
        'labor_billed_hours' => $created['labor_billed_hours'],
        'labor_rate_cents' => $created['labor_rate_cents'],
        'policy_resolved_labor_rate_cents' => $created['policy_resolved_labor_rate_cents'],
        'resolved_from_posture' => $created['resolved_from_posture'],
        'resolved_from_operation_class' => $created['resolved_from_operation_class'],
        'labor_policy_id' => $created['labor_policy_id'],
        'labor_policy_version' => $created['labor_policy_version'],
        'operation_id' => $mechanical->id,
        'subtotal_cents' => $created['unit_price_cents'],
        'tax_cents' => 0,
        'shop_fee_cents' => 0,
        'standing_discount_cents' => 0,
        'total_cents' => $created['unit_price_cents'],
    ]);

    $updated = app(LaborAuthority::class)->resolveForLine([
        'labor_category_key' => 'diagnostic',
        'labor_entered_hours' => '2.00',
        'labor_adjustment' => LaborAdjustment::Normal->value,
    ], ShopSettings::current(), $line->fresh());

    expect($updated)
        ->labor_rate_cents->toBe((int) $line->labor_rate_cents)
        ->resolved_from_operation_class->toBe('general_repair')
        ->operation_id->toBe($mechanical->id);
});

test('saving repairpal policy updates only the edited cell', function () {
    $diagnostics = OperationClass::query()->where('key', 'diagnostics')->firstOrFail();
    $general = OperationClass::query()->where('key', 'general_repair')->firstOrFail();

    app(UpsertLaborPolicyAction::class)->execute(
        ConcernBillingPosture::RepairPal,
        $diagnostics,
        155.00,
        Carbon::parse('2026-07-20'),
        'single cell',
    );

    $resolver = app(LaborPolicyResolver::class);

    expect($resolver->resolve(ConcernBillingPosture::RepairPal, 'diagnostics')->hourlyRateCents)->toBe(15500)
        ->and($resolver->resolve(ConcernBillingPosture::RepairPal, 'general_repair')->hourlyRateCents)->toBe(15000);
});
