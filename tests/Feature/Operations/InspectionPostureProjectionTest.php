<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Inspections\ApplyInspectionTemplateAction;
use App\Ark\Operations\Inspections\DefaultInspectionTemplateCatalog;
use App\Ark\Operations\Inspections\EnsureInspectionAction;
use App\Ark\Operations\Inspections\InspectionChecklistStatus;
use App\Ark\Operations\Inspections\InspectionObservedState;
use App\Ark\Operations\Inspections\InspectionPosture;
use App\Ark\Operations\Inspections\InspectionPostureProjection;
use App\Ark\Operations\Inspections\UpdateInspectionChecklistItemAction;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
});

function inspectionPostureRepairOrder(?User $technician = null): RepairOrder
{
    $customer = Customer::query()->create([
        'first_name' => 'Posture',
        'last_name' => 'Customer',
        'mobile_phone' => '7195550188',
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2016,
        'make' => 'Toyota',
        'model' => 'Camry',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::InProgress,
        'concern_summary' => 'Inspection posture RO',
        'assigned_technician_id' => $technician?->id,
    ]);

    return $repairOrder;
}

test('inspection posture starts not started then moves through progress and complete', function () {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = inspectionPostureRepairOrder($technician);

    $posture = app(InspectionPostureProjection::class)->forRepairOrder($repairOrder);
    expect($posture->key)->toBe(InspectionPosture::NOT_STARTED)
        ->and($posture->label())->toBe('Not Started');

    $inspection = app(EnsureInspectionAction::class)->execute($repairOrder, $technician);
    DefaultInspectionTemplateCatalog::seedIfMissing();
    app(ApplyInspectionTemplateAction::class)->execute($repairOrder, $inspection, actor: $technician);

    $item = $inspection->items()->where('label', 'Wipers / washer')->firstOrFail();
    app(UpdateInspectionChecklistItemAction::class)->execute(
        repairOrder: $repairOrder,
        inspection: $inspection,
        item: $item,
        status: InspectionChecklistStatus::Good,
        actor: $technician,
    );

    $inProgress = app(InspectionPostureProjection::class)->forRepairOrder($repairOrder->fresh());
    expect($inProgress->key)->toBe(InspectionPosture::IN_PROGRESS)
        ->and($inProgress->headline)->toBe('In Progress')
        ->and($inProgress->percentComplete)->toBeGreaterThan(0)
        ->and($inProgress->percentComplete)->toBeLessThan(100)
        ->and($inProgress->label())->toStartWith('In Progress · ');

    $inspection->fresh()->items()
        ->whereNotNull('inspection_template_item_id')
        ->where('observed_state', InspectionObservedState::NotChecked->value)
        ->each(function ($checklistItem): void {
            $checklistItem->forceFill([
                'observed_state' => InspectionObservedState::Na->value,
            ])->save();
        });

    $complete = app(InspectionPostureProjection::class)->forRepairOrder($repairOrder->fresh());
    expect($complete->key)->toBe(InspectionPosture::COMPLETE)
        ->and($complete->label())->toBe('Complete')
        ->and($complete->attentionCount)->toBe(0);
});

test('inspection posture needs review when walk is complete with attention findings', function () {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = inspectionPostureRepairOrder($technician);

    $inspection = app(EnsureInspectionAction::class)->execute($repairOrder, $technician);
    DefaultInspectionTemplateCatalog::seedIfMissing();
    app(ApplyInspectionTemplateAction::class)->execute($repairOrder, $inspection, actor: $technician);

    // Address every visible point (N/A) so the walk is complete, then add an
    // attention finding that remains addressed (no required slots / evidence).
    $inspection->forceFill(['rear_axle_brake_type' => 'disc'])->save();
    $inspection->fresh()->items()
        ->whereNotNull('inspection_template_item_id')
        ->each(function ($checklistItem): void {
            $checklistItem->forceFill([
                'observed_state' => InspectionObservedState::Na->value,
            ])->save();
        });

    expect(app(InspectionPostureProjection::class)->forRepairOrder($repairOrder->fresh())->key)
        ->toBe(InspectionPosture::COMPLETE);

    $item = $inspection->fresh()->items()->where('label', 'Wipers / washer')->firstOrFail();
    $item->forceFill([
        'observed_state' => InspectionObservedState::Monitor->value,
        'notes' => 'Streaking — replace soon.',
    ])->save();

    $posture = app(InspectionPostureProjection::class)->forRepairOrder($repairOrder->fresh());
    expect($posture->key)->toBe(InspectionPosture::NEEDS_REVIEW)
        ->and($posture->label())->toBe('Needs Review')
        ->and($posture->attentionCount)->toBeGreaterThan(0)
        ->and($posture->detail)->toContain('need')
        ->and($posture->remaining)->toBe(0);
});

test('advisor estimate review renders shared inspection posture on the review toolbar', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = inspectionPostureRepairOrder();

    $this->actingAs($advisor)
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('ops-review-toolbar-section--visit-signals', false)
        ->assertSee('data-inspection-posture="not_started"', false)
        ->assertSee('Not Started', false);
});
