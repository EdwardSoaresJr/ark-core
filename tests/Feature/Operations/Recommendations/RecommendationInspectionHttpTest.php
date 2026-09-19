<?php

use App\Ark\Operations\Inspections\EnsureInspectionAction;
use App\Ark\Operations\Inspections\InspectionItem;
use App\Ark\Operations\Inspections\InspectionItemCategory;
use App\Ark\Operations\Inspections\InspectionObservedState;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
});

test('inspection item can create a recommendation from the walk action', function () {
    $customer = Customer::query()->create([
        'first_name' => 'Walk',
        'last_name' => 'Inspect',
        'phone' => '555-0110',
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2014,
        'make' => 'Toyota',
        'model' => 'Camry',
    ]);
    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'mileage_in' => 142000,
        'concern_summary' => 'Inspection',
    ]);

    $actor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $inspection = app(EnsureInspectionAction::class)->execute($repairOrder, $actor);

    $item = InspectionItem::query()->create([
        'inspection_id' => $inspection->id,
        'category' => InspectionItemCategory::Steering->value,
        'label' => 'LF outer tie rod',
        'observed_state' => InspectionObservedState::Fail,
        'notes' => '[Safety] Excessive play',
        'position' => 1,
    ]);

    $this->actingAs($actor)
        ->post(route('operations.repair-orders.inspection.items.recommendations.store', [$repairOrder, $item]), [
            'safety_related' => '1',
            'due_kind' => 'now',
        ])
        ->assertRedirect();

    $recommendation = \App\Ark\Operations\Recommendations\Recommendation::query()
        ->where('originating_inspection_item_id', $item->id)
        ->first();

    expect($recommendation)->not->toBeNull()
        ->and($recommendation->title)->toContain('tie rod')
        ->and($recommendation->safety_related)->toBeTrue()
        ->and($item->fresh()->id)->toBe($item->id)
        ->and($item->fresh()->label)->toBe('LF outer tie rod');
});
