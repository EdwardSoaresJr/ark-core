<?php

use App\Ark\Operations\EstimatePricing\LaborPoliciesMatrixProjection;
use App\Ark\Operations\EstimatePricing\LaborPolicy;
use App\Ark\Operations\EstimatePricing\LaborPolicyResolver;
use App\Ark\Operations\EstimatePricing\OperationClass;
use App\Ark\Operations\RepairOrders\ConcernBillingPosture;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
});

test('admin can view labor policies matrix on shop settings', function () {
    $this->actingAs($this->admin)
        ->get(route('operations.settings.shop.edit', [
            'section' => 'financial',
            'financial-tab' => 'labor-policies',
        ]))
        ->assertOk()
        ->assertSee('Labor Policies')
        ->assertSee('Resolver Preview')
        ->assertSee('Maintenance')
        ->assertSee('Customer pay')
        ->assertSee('Resolved Rate');
});

test('admin can save a single labor policy cell', function () {
    $class = OperationClass::query()->where('key', 'maintenance')->firstOrFail();

    $this->actingAs($this->admin)
        ->patch(route('operations.settings.shop.labor-policies.update'), [
            'billing_posture' => ConcernBillingPosture::CustomerPay->value,
            'operation_class_key' => 'maintenance',
            'hourly_rate' => '145.00',
            'effective_from' => '2026-07-20',
            'change_reason' => 'Phase 3 matrix edit',
        ])
        ->assertRedirect(route('operations.settings.shop.edit', [
            'section' => 'financial',
            'financial-tab' => 'labor-policies',
        ]));

    $policy = LaborPolicy::query()
        ->where('billing_posture', ConcernBillingPosture::CustomerPay->value)
        ->where('operation_class_id', $class->id)
        ->whereDate('effective_from', '2026-07-20')
        ->orderByDesc('id')
        ->first();

    expect($policy)->not->toBeNull()
        ->and($policy->hourly_rate_cents)->toBe(14500)
        ->and($policy->change_reason)->toBe('Phase 3 matrix edit');

    $resolved = app(LaborPolicyResolver::class)->resolve(
        ConcernBillingPosture::CustomerPay,
        'maintenance',
        Carbon::parse('2026-07-20'),
    );

    expect($resolved->hourlyRateCents)->toBe(14500);
});

test('labor policy settings require settings manage permission', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $this->actingAs($advisor)
        ->patch(route('operations.settings.shop.labor-policies.update'), [
            'billing_posture' => ConcernBillingPosture::CustomerPay->value,
            'operation_class_key' => 'maintenance',
            'hourly_rate' => '145.00',
            'effective_from' => '2026-07-20',
        ])
        ->assertRedirect(route('operations.index'))
        ->assertSessionHasErrors('settings');
});

test('resolver preview query params show resolved rate', function () {
    $this->actingAs($this->admin)
        ->get(route('operations.settings.shop.edit', [
            'section' => 'financial',
            'financial-tab' => 'labor-policies',
            'lp_posture' => ConcernBillingPosture::Internal->value,
            'lp_class' => 'general_repair',
        ]))
        ->assertOk()
        ->assertSee('$0.00');
});

test('labor policies shows default labor category as read-only from labor categories', function () {
    $this->actingAs($this->admin)
        ->get(route('operations.settings.shop.edit', [
            'section' => 'financial',
            'financial-tab' => 'labor-policies',
        ]))
        ->assertOk()
        ->assertSee('Default labor category')
        ->assertSee('Owned by Labor Categories')
        ->assertSee('Mechanical')
        ->assertDontSee('Save default');

    expect(\Illuminate\Support\Facades\Route::has('operations.settings.shop.labor-policies.default'))->toBeFalse();

    $matrix = app(LaborPoliciesMatrixProjection::class)->build();
    expect($matrix['shop_default']['labor_category_key'])->toBe('mechanical')
        ->and(collect($matrix['operation_classes'])->firstWhere('key', 'general_repair')['is_shop_default'])->toBeTrue();
});
