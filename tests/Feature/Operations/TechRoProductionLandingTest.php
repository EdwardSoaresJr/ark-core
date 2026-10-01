<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Inspections\ApplyInspectionTemplateAction;
use App\Ark\Operations\Inspections\DefaultInspectionTemplateCatalog;
use App\Ark\Operations\Inspections\EnsureInspectionAction;
use App\Ark\Operations\Inspections\InspectionChecklistStatus;
use App\Ark\Operations\Inspections\InspectionObservedState;
use App\Ark\Operations\Inspections\UpdateInspectionChecklistItemAction;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
});

test('technician show lands on production work order not estimate review', function () {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = techLandingRepairOrder($technician);

    $this->actingAs($technician)
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('data-technician-production-landing', false)
        ->assertSee('Work order', false)
        ->assertSee('2014 Subaru Outback', false)
        ->assertSee('Why it’s here', false)
        ->assertSee('Check engine light on highway.', false)
        ->assertSee('Check engine light', false)
        ->assertSee('Open Inspection', false)
        ->assertSee('data-inspection-capture-cta', false)
        ->assertSee('Bay layout', false)
        ->assertDontSee('Open Tablet View', false)
        ->assertSee(route('operations.repair-orders.inspection.show', $repairOrder), false)
        ->assertDontSee('ops-estimate-workspace--review', false)
        ->assertDontSee('Approval Forecast', false)
        ->assertDontSee('ops-inspection-walk', false);
});

test('technician continue and open inspection labels follow coverage', function () {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = techLandingRepairOrder($technician);

    $inspection = app(EnsureInspectionAction::class)->execute($repairOrder, $technician);
    DefaultInspectionTemplateCatalog::seedIfMissing();
    app(ApplyInspectionTemplateAction::class)->execute($repairOrder, $inspection, actor: $technician);
    $item = $inspection->items()->whereNotNull('inspection_template_item_id')->orderBy('id')->firstOrFail();

    app(UpdateInspectionChecklistItemAction::class)->execute(
        repairOrder: $repairOrder,
        inspection: $inspection,
        item: $item,
        status: InspectionChecklistStatus::Good,
        actor: $technician,
    );

    $this->actingAs($technician)
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Continue Inspection', false)
        ->assertSee('In Progress', false)
        ->assertSee('1 of ', false)
        ->assertDontSee('Done', false)
        ->assertDontSee('Finish Inspection', false);

    // N/A addresses SM points so coverage can reach Open without fabricating pad/tread values.
    $inspection->fresh()->items()
        ->whereNotNull('inspection_template_item_id')
        ->where('observed_state', InspectionObservedState::NotChecked->value)
        ->each(function ($checklistItem): void {
            $checklistItem->forceFill([
                'observed_state' => InspectionObservedState::Na->value,
            ])->save();
        });
    $inspection->forceFill(['rear_axle_brake_type' => 'disc'])->save();

    $this->actingAs($technician)
        ->get(route('operations.repair-orders.show', $repairOrder->fresh()))
        ->assertOk()
        ->assertSee('Open Inspection', false)
        ->assertSee('Complete', false)
        ->assertDontSee('Continue Inspection', false)
        ->assertDontSee('Finish Inspection', false);
});

test('advisor and admin keep estimate review on show', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
    $repairOrder = techLandingRepairOrder();

    $this->actingAs($advisor)
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('ops-estimate-workspace--review', false)
        ->assertDontSee('data-technician-production-landing', false);

    $this->actingAs($admin)
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('ops-estimate-workspace--review', false)
        ->assertDontSee('data-technician-production-landing', false);
});

test('multi-role advisor technician keeps estimate review', function () {
    $user = User::factory()->create()->assignRole([
        ArkRole::Advisor->value,
        ArkRole::Technician->value,
    ]);
    $repairOrder = techLandingRepairOrder($user);

    $this->actingAs($user)
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('ops-estimate-workspace--review', false)
        ->assertDontSee('data-technician-production-landing', false);
});

test('technician estimate-review url also uses production landing', function () {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = techLandingRepairOrder($technician);

    $this->actingAs($technician)
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('data-technician-production-landing', false)
        ->assertDontSee('ops-estimate-workspace--review', false);
});

function techLandingRepairOrder(?User $assignedTechnician = null): RepairOrder
{
    $customer = Customer::query()->create([
        'first_name' => 'Landing',
        'last_name' => 'Customer',
        'phone' => '7195554411',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'plate' => 'LAND1',
        'year' => 2014,
        'make' => 'Subaru',
        'model' => 'Outback',
        'vin' => '4S4BRBCC5E1234599',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::InProgress,
        'concern_summary' => 'Production discipline inspection.',
        'visit_reason' => 'Check engine light on highway.',
    ]);

    RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Check engine light',
        'disposition' => 'draft',
        'position' => 0,
    ]);

    if ($assignedTechnician !== null) {
        $repairOrder->forceFill(['assigned_technician_id' => $assignedTechnician->id])->save();
    }

    return $repairOrder->fresh(['vehicle', 'customer', 'concerns']);
}
