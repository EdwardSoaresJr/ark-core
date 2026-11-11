<?php

use App\Ark\Operations\EstimatePricing\LaborPolicy;
use App\Ark\Operations\EstimatePricing\LaborPolicyResolver;
use App\Ark\Operations\EstimatePricing\LaborRateType;
use App\Ark\Operations\EstimatePricing\OperationClass;
use App\Ark\Operations\RepairOrders\ConcernBillingPosture;
use Illuminate\Support\Carbon;

/**
 * Existing shops (including alpha staging) still store rate_type = 'standard' on
 * labor_policies. Enum casting must accept that value or estimate pricing 500s.
 */
test('labor rate type accepts legacy standard shop configuration values', function () {
    expect(LaborRateType::tryFrom('standard'))->toBe(LaborRateType::Standard)
        ->and(LaborRateType::Standard->value)->toBe('standard')
        ->and(LaborRateType::Standard->resolvesToHourlyCents())->toBeTrue();
});

test('labor policy resolver prices existing standard rate_type rows', function () {
    $class = OperationClass::query()->where('key', 'maintenance')->firstOrFail();

    LaborPolicy::query()->create([
        'billing_posture' => 'customer_pay',
        'operation_class_id' => $class->id,
        'rate_type' => LaborRateType::Standard,
        'hourly_rate_cents' => 16500,
        'effective_from' => '2026-01-01',
        'effective_until' => null,
        'priority' => 50,
        'version' => 99,
    ]);

    $resolved = app(LaborPolicyResolver::class)->resolve(
        ConcernBillingPosture::CustomerPay,
        'maintenance',
        Carbon::parse('2026-07-20'),
    );

    expect($resolved->rateType)->toBe(LaborRateType::Standard)
        ->and($resolved->hourlyRateCents)->toBe(16500)
        ->and($resolved->laborPolicyVersion)->toBe(99);
});

test('casting a labor policy with standard rate_type does not throw', function () {
    $class = OperationClass::query()->where('key', 'diagnostics')->firstOrFail();

    $policy = LaborPolicy::query()->create([
        'billing_posture' => 'customer_pay',
        'operation_class_id' => $class->id,
        'rate_type' => 'standard',
        'hourly_rate_cents' => 14900,
        'effective_from' => '2020-01-01',
        'effective_until' => null,
        'priority' => 1,
        'version' => 1,
    ]);

    $fresh = LaborPolicy::query()->findOrFail($policy->id);

    expect($fresh->rate_type)->toBe(LaborRateType::Standard)
        ->and($fresh->hourly_rate_cents)->toBe(14900);
});
