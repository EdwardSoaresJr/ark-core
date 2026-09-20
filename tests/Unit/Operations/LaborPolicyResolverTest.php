<?php

use App\Ark\Operations\EstimatePricing\LaborPolicy;
use App\Ark\Operations\EstimatePricing\LaborPolicyResolver;
use App\Ark\Operations\EstimatePricing\LaborRateType;
use App\Ark\Operations\EstimatePricing\OperationClass;
use App\Ark\Operations\RepairOrders\ConcernBillingPosture;
use Illuminate\Support\Carbon;
use Tests\TestCase;


test('labor policy resolver returns retail maintenance hourly rate', function () {
    $resolved = app(LaborPolicyResolver::class)->resolve(
        ConcernBillingPosture::CustomerPay,
        'maintenance',
        Carbon::parse('2026-07-20'),
    );

    expect($resolved->rateType)->toBe(LaborRateType::Hourly)
        ->and($resolved->billingPosture)->toBe('customer_pay')
        ->and($resolved->operationClassKey)->toBe('maintenance')
        ->and($resolved->laborPolicyId)->not->toBeNull()
        ->and($resolved->laborPolicyVersion)->toBe(1)
        ->and($resolved->hourlyRateCents)->toBeGreaterThan(0);
});

test('labor policy resolver maps default posture to customer pay', function () {
    $resolved = app(LaborPolicyResolver::class)->resolve(
        ConcernBillingPosture::Default,
        'diagnostics',
    );

    expect($resolved->billingPosture)->toBe('customer_pay')
        ->and($resolved->operationClassKey)->toBe('diagnostics');
});

test('labor policy resolver returns zero for internal', function () {
    $resolved = app(LaborPolicyResolver::class)->resolve(
        ConcernBillingPosture::Internal,
        'general_repair',
    );

    expect($resolved->rateType)->toBe(LaborRateType::Zero)
        ->and($resolved->hourlyRateCents)->toBe(0);
});

test('labor policy resolver returns contract for repairpal', function () {
    $resolved = app(LaborPolicyResolver::class)->resolve(
        ConcernBillingPosture::RepairPal,
        'advanced_mechanical',
    );

    expect($resolved->rateType)->toBe(LaborRateType::Contract)
        ->and($resolved->hourlyRateCents)->toBe(15000);
});

test('labor policy resolver prefers higher priority policy', function () {
    $class = OperationClass::query()->where('key', 'maintenance')->firstOrFail();

    LaborPolicy::query()->create([
        'billing_posture' => 'customer_pay',
        'operation_class_id' => $class->id,
        'rate_type' => LaborRateType::Hourly,
        'hourly_rate_cents' => 99900,
        'effective_from' => '2026-01-01',
        'effective_until' => null,
        'priority' => 10,
        'version' => 2,
    ]);

    $resolved = app(LaborPolicyResolver::class)->resolve(
        ConcernBillingPosture::CustomerPay,
        'maintenance',
        Carbon::parse('2026-07-20'),
    );

    expect($resolved->hourlyRateCents)->toBe(99900)
        ->and($resolved->laborPolicyVersion)->toBe(2);
});

test('resolved labor rate snapshot shape', function () {
    $resolved = app(LaborPolicyResolver::class)->resolve(
        ConcernBillingPosture::Fleet,
        'general_repair',
    );

    $snapshot = $resolved->toLineSnapshot();

    expect($snapshot)->toHaveKeys([
        'labor_rate_cents',
        'resolved_from_posture',
        'resolved_from_operation_class',
        'labor_policy_id',
        'labor_policy_version',
    ])->and($snapshot['resolved_from_posture'])->toBe('fleet')
        ->and($snapshot['resolved_from_operation_class'])->toBe('general_repair');
});

test('labor policy resolver rejects unknown operation class', function () {
    app(LaborPolicyResolver::class)->resolve(
        ConcernBillingPosture::CustomerPay,
        'not_a_class',
    );
})->throws(RuntimeException::class);
