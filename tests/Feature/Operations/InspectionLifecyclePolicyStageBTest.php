<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Events\OperationalEvent;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Inspections\GuardInspectionCustomerPublication;
use App\Ark\Operations\Inspections\InspectionRequirement;
use App\Ark\Operations\Inspections\InspectionRequirementResolver;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderOperationalOutstandingWork;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
});

function stageBRo(RepairOrderStatus $status = RepairOrderStatus::InProgress): RepairOrder
{
    $customer = Customer::query()->create([
        'first_name' => 'StageB',
        'last_name' => 'Policy',
        'phone' => '55502842',
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2019,
        'make' => 'Honda',
        'model' => 'Civic',
    ]);

    return RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => $status,
        'concern_summary' => 'Stage B policy',
        'mileage_in' => 40000,
    ]);
}

test('settings manager can save shop inspection lifecycle policy', function () {
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);

    $this->actingAs($admin)
        ->patch(route('operations.settings.shop.inspection-lifecycle-policy.update'), [
            'inspection_lifecycle_policy' => InspectionRequirement::Required->value,
        ])
        ->assertRedirect(route('operations.settings.shop.edit', [
            'section' => 'workflow',
            'workflow-tab' => 'inspections',
        ]))
        ->assertSessionHas('status');

    expect(ShopSettings::current()->fresh()->inspection_lifecycle_policy)
        ->toBe(InspectionRequirement::Required->value)
        ->and(app(InspectionRequirementResolver::class)->shopPolicy())
        ->toBe(InspectionRequirement::Required);
});

test('technician cannot change shop inspection lifecycle policy', function () {
    $tech = User::factory()->create()->assignRole(ArkRole::Technician->value);

    $this->actingAs($tech)
        ->from(route('operations.settings.shop.edit', ['section' => 'workflow', 'workflow-tab' => 'inspections']))
        ->patch(route('operations.settings.shop.inspection-lifecycle-policy.update'), [
            'inspection_lifecycle_policy' => InspectionRequirement::Required->value,
        ])
        ->assertRedirect();

    expect(ShopSettings::current()->fresh()->inspection_lifecycle_policy ?? 'optional')
        ->not->toBe(InspectionRequirement::Required->value);
});

test('ro override survives later shop policy change', function () {
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
    $this->actingAs($admin);

    ShopSettings::current()->persistTrusted([
        'inspection_lifecycle_policy' => InspectionRequirement::Optional->value,
    ]);

    $repairOrder = stageBRo();

    $this->patch(route('operations.repair-orders.inspection.requirement.update', $repairOrder), [
        'inspection_lifecycle_policy' => InspectionRequirement::Required->value,
    ])->assertRedirect();

    ShopSettings::current()->persistTrusted([
        'inspection_lifecycle_policy' => InspectionRequirement::Optional->value,
    ]);

    $decision = app(InspectionRequirementResolver::class)->forRepairOrder($repairOrder->fresh());

    expect($decision->requirement)->toBe(InspectionRequirement::Required)
        ->and($decision->source)->toBe('repair_order_override')
        ->and($decision->blocksLifecycleOnIncomplete())->toBeTrue();
});

test('admin can waive required inspection with auditable reason', function () {
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
    $this->actingAs($admin);

    $repairOrder = stageBRo();
    $repairOrder->forceFill([
        'inspection_lifecycle_policy' => InspectionRequirement::Required->value,
    ])->save();

    $this->post(route('operations.repair-orders.inspection.waiver.store', $repairOrder), [
        'inspection_waived_reason' => 'Customer declined full inspection today.',
    ])->assertRedirect();

    $fresh = $repairOrder->fresh();
    $decision = app(InspectionRequirementResolver::class)->forRepairOrder($fresh);

    expect($fresh->inspection_waived_at)->not->toBeNull()
        ->and($fresh->inspection_waived_by)->toBe($admin->id)
        ->and($fresh->inspection_waived_reason)->toBe('Customer declined full inspection today.')
        ->and($decision->waived)->toBeTrue()
        ->and($decision->blocksLifecycleOnIncomplete())->toBeFalse()
        ->and(collect(app(RepairOrderOperationalOutstandingWork::class)->items($fresh))
            ->where('kind', RepairOrderOperationalOutstandingWork::KIND_INSPECTION_IN_PROGRESS))->toBeEmpty();
});

test('advisor cannot waive required inspection', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $this->actingAs($advisor);

    $repairOrder = stageBRo();
    $repairOrder->forceFill([
        'inspection_lifecycle_policy' => InspectionRequirement::Required->value,
    ])->save();

    $this->post(route('operations.repair-orders.inspection.waiver.store', $repairOrder), [
        'inspection_waived_reason' => 'Trying to waive without admin.',
    ])->assertForbidden();
});

test('optional inspection does not accept a waiver', function () {
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
    $this->actingAs($admin);

    $repairOrder = stageBRo();

    $this->post(route('operations.repair-orders.inspection.waiver.store', $repairOrder), [
        'inspection_waived_reason' => 'Should not be needed for optional.',
    ])->assertSessionHasErrors('inspection_waived_reason');
});

test('inspection settings panel renders policy choices', function () {
    $html = view('operations.settings.partials.inspection-lifecycle-policy', [
        'settings' => ShopSettings::current(),
        'errors' => new \Illuminate\Support\ViewErrorBag([]),
    ])->render();

    expect($html)
        ->toContain('Optional')
        ->toContain('Recommended')
        ->toContain('Required')
        ->toContain('never block completing');
});

test('override and waiver record operational events', function () {
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
    $this->actingAs($admin);

    $repairOrder = stageBRo();

    $this->patch(route('operations.repair-orders.inspection.requirement.update', $repairOrder), [
        'inspection_lifecycle_policy' => InspectionRequirement::Required->value,
    ])->assertRedirect();

    expect(OperationalEvent::query()
        ->where('event_name', OperationalEventName::InspectionRequirementOverridden->value)
        ->where('aggregate_id', $repairOrder->id)
        ->exists())->toBeTrue();

    $this->post(route('operations.repair-orders.inspection.waiver.store', $repairOrder), [
        'inspection_waived_reason' => 'Customer declined full inspection today.',
    ])->assertRedirect();

    expect(OperationalEvent::query()
        ->where('event_name', OperationalEventName::InspectionRequirementWaived->value)
        ->where('aggregate_id', $repairOrder->id)
        ->exists())->toBeTrue();
});

test('customer publication copy does not say required for incomplete coverage', function () {
    $repairOrder = stageBRo();

    $reason = app(GuardInspectionCustomerPublication::class)->blockReason($repairOrder);

    expect($reason)->toContain('Record at least one inspection finding')
        ->and($reason)->not->toContain('Finish required inspection work');
});
