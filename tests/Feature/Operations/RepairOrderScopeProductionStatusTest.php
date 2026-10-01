<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Events\OperationalEvent;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\RepairOrders\PartProcurementState;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\RepairOrders\ScopeProductionStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
});

test('approved scopes expose per-scope production status control on builder', function () {
    $repairOrder = scopeProductionRepairOrder();

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('name="production_status"', false)
        ->assertSee('Completed', false);
});

test('production status ajax update on builder refreshes via redirect to edit', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $repairOrder = scopeProductionRepairOrder();
    $concern = $repairOrder->concerns()->firstOrFail();

    $this->actingAs($advisor)
        ->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'text/html',
        ])
        ->patch(route('operations.repair-orders.concerns.production-status', [$repairOrder, $concern]), [
            'production_status' => ScopeProductionStatus::InProgress->value,
        ])
        ->assertRedirect(route('operations.repair-orders.show', $repairOrder).'#concern-'.$concern->id);

    expect($concern->fresh()->productionStatus())->toBe(ScopeProductionStatus::InProgress);
});

test('production status ajax update on estimate review redirects to estimate review', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $repairOrder = scopeProductionRepairOrder();
    $concern = $repairOrder->concerns()->firstOrFail();

    $this->actingAs($advisor)
        ->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'text/html',
        ])
        ->patch(route('operations.repair-orders.concerns.production-status', [$repairOrder, $concern]), [
            'production_status' => ScopeProductionStatus::WaitingParts->value,
            'return_mode' => 'review',
        ])
        ->assertRedirect(route('operations.repair-orders.estimate-review', $repairOrder).'#concern-'.$concern->id);

    expect($concern->fresh()->productionStatus())->toBe(ScopeProductionStatus::WaitingParts);
});

test('technicians can mark an approved scope completed', function () {
    $technician = actingAsLearnCurrentStaff(ArkRole::Technician);
    $repairOrder = scopeProductionRepairOrder();
    $concern = $repairOrder->concerns()->firstOrFail();
    $repairOrder->forceFill(['assigned_technician_id' => $technician->id])->save();

    $this->actingAs($technician)
        ->patch(route('operations.repair-orders.concerns.production-status', [$repairOrder, $concern]), [
            'production_status' => ScopeProductionStatus::Completed->value,
        ])
        ->assertRedirect(route('operations.repair-orders.show', $repairOrder).'#concern-'.$concern->id);

    expect($concern->fresh()->productionStatus())->toBe(ScopeProductionStatus::Completed);

    $event = OperationalEvent::query()
        ->where('event_name', OperationalEventName::ConcernProductionStatusChanged->value)
        ->latest('id')
        ->first();

    expect($event)->not->toBeNull()
        ->and($event->payload_json['concern_id'])->toBe($concern->id)
        ->and($event->payload_json['new_production_status'])->toBe('completed');
});

test('production status can be set on draft and recommended scopes', function () {
    $repairOrder = scopeProductionRepairOrder();
    $concern = $repairOrder->concerns()->firstOrFail();

    foreach ([RepairOrderConcernDisposition::Draft, RepairOrderConcernDisposition::Recommended] as $disposition) {
        $concern->update(['disposition' => $disposition, 'production_status' => ScopeProductionStatus::Pending]);

        $this->actingAs(actingAsLearnCurrentAdvisor())
            ->patch(route('operations.repair-orders.concerns.production-status', [$repairOrder, $concern]), [
                'production_status' => ScopeProductionStatus::InProgress->value,
            ])
            ->assertRedirect(route('operations.repair-orders.show', $repairOrder).'#concern-'.$concern->id);

        expect($concern->fresh()->productionStatus())->toBe(ScopeProductionStatus::InProgress);
    }
});

test('production status cannot be set on deferred or declined scopes', function () {
    $repairOrder = scopeProductionRepairOrder();
    $concern = $repairOrder->concerns()->firstOrFail();
    $concern->update(['disposition' => RepairOrderConcernDisposition::Deferred]);

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->patch(route('operations.repair-orders.concerns.production-status', [$repairOrder, $concern]), [
            'production_status' => ScopeProductionStatus::InProgress->value,
        ])
        ->assertSessionHasErrors('production_status');
});

test('ordering parts on an approved scope moves production to waiting parts', function () {
    $repairOrder = scopeProductionRepairOrder();
    $concern = $repairOrder->concerns()->firstOrFail();
    $line = $repairOrder->lines()->where('type', RepairOrderLineType::Part)->firstOrFail();

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->patch(route('operations.repair-orders.lines.procurement.update', [$repairOrder, $line]), [
            'procurement_state' => PartProcurementState::Ordered->value,
        ])
        ->assertRedirect();

    expect($concern->fresh()->productionStatus())->toBe(ScopeProductionStatus::WaitingParts);
});

test('receiving all parts on a scope moves production from waiting parts to pending', function () {
    $repairOrder = scopeProductionRepairOrder();
    $concern = $repairOrder->concerns()->firstOrFail();
    $line = $repairOrder->lines()->where('type', RepairOrderLineType::Part)->firstOrFail();
    $concern->update(['production_status' => ScopeProductionStatus::WaitingParts]);
    $line->update(['procurement_state' => PartProcurementState::Ordered]);

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->patch(route('operations.repair-orders.lines.procurement.update', [$repairOrder, $line]), [
            'procurement_state' => PartProcurementState::Received->value,
        ])
        ->assertRedirect();

    expect($concern->fresh()->productionStatus())->toBe(ScopeProductionStatus::Pending);
});

test('parts automation does not override completed scope production status', function () {
    $repairOrder = scopeProductionRepairOrder();
    $concern = $repairOrder->concerns()->firstOrFail();
    $line = $repairOrder->lines()->where('type', RepairOrderLineType::Part)->firstOrFail();
    $concern->update(['production_status' => ScopeProductionStatus::Completed]);

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->patch(route('operations.repair-orders.lines.procurement.update', [$repairOrder, $line]), [
            'procurement_state' => PartProcurementState::Ordered->value,
        ])
        ->assertRedirect();

    expect($concern->fresh()->productionStatus())->toBe(ScopeProductionStatus::Completed);
});

test('parts automation updates recommended scopes', function () {
    $repairOrder = scopeProductionRepairOrder();
    $concern = $repairOrder->concerns()->firstOrFail();
    $line = $repairOrder->lines()->where('type', RepairOrderLineType::Part)->firstOrFail();
    $concern->update(['disposition' => RepairOrderConcernDisposition::Recommended]);

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->patch(route('operations.repair-orders.lines.procurement.update', [$repairOrder, $line]), [
            'procurement_state' => PartProcurementState::Ordered->value,
        ])
        ->assertRedirect();

    expect($concern->fresh()->productionStatus())->toBe(ScopeProductionStatus::WaitingParts);
});

test('parts automation ignores deferred scopes', function () {
    $repairOrder = scopeProductionRepairOrder();
    $concern = $repairOrder->concerns()->firstOrFail();
    $line = $repairOrder->lines()->where('type', RepairOrderLineType::Part)->firstOrFail();
    $concern->update(['disposition' => RepairOrderConcernDisposition::Deferred]);

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->patch(route('operations.repair-orders.lines.procurement.update', [$repairOrder, $line]), [
            'procurement_state' => PartProcurementState::Ordered->value,
        ])
        ->assertRedirect();

    expect($concern->fresh()->productionStatus())->toBe(ScopeProductionStatus::Pending);
});

test('revoking approval resets scope production status to pending', function () {
    $repairOrder = scopeProductionRepairOrder();
    $concern = $repairOrder->concerns()->firstOrFail();
    $concern->update(['production_status' => ScopeProductionStatus::Completed]);

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->patch(route('operations.repair-orders.concerns.disposition', [$repairOrder, $concern]), [
            'disposition' => RepairOrderConcernDisposition::Deferred->value,
        ])
        ->assertRedirect();

    expect($concern->fresh()->productionStatus())->toBe(ScopeProductionStatus::Pending);
});

function scopeProductionRepairOrder(): RepairOrder
{
    $customer = Customer::query()->create([
        'first_name' => 'Scope',
        'last_name' => 'Production',
        'phone' => '555-0198',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2019,
        'make' => 'Honda',
        'model' => 'Pilot',
        'vin' => '5FNRL6H74KB123456',
        'normalized_vin' => '5FNRL6H74KB123456',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::InProgress,
        'concern_summary' => 'Brakes and suspension scopes.',
    ]);

    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Front brakes pulse',
        'disposition' => RepairOrderConcernDisposition::Approved,
        'position' => 1,
    ]);

    $repairOrder->lines()->create([
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Part,
        'description' => 'Front brake pads',
        'quantity' => '1.00',
        'unit_price_cents' => 9800,
        'part_cost_cents' => 5300,
        'vendor_name' => 'Local Parts Counter',
        'part_number' => 'PAD-123',
        'subtotal_cents' => 9800,
        'tax_cents' => 0,
        'total_cents' => 9800,
    ]);

    $repairOrder->lines()->create([
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Front brake service',
        'quantity' => '1.50',
        'labor_billed_hours' => '1.50',
        'unit_price_cents' => 15000,
        'subtotal_cents' => 22500,
        'tax_cents' => 0,
        'total_cents' => 22500,
    ]);

    return $repairOrder->fresh(['concerns', 'lines.concern']);
}
