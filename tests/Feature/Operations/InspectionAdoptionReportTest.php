<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Inspections\EnsureInspectionAction;
use App\Ark\Operations\Inspections\InspectionAdoptionReport;
use App\Ark\Operations\Inspections\InspectionFindingIntent;
use App\Ark\Operations\Inspections\InspectionItem;
use App\Ark\Operations\Inspections\InspectionItemMeasurement;
use App\Ark\Operations\Inspections\InspectionObservedState;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Models\User;

test('inspection adoption report summarizes open repair order usage', function () {
    $technician = User::factory()->create();

    $engagedOrder = adoptionRepairOrder(RepairOrderStatus::InProgress, 'ENG1');
    $openedOnlyOrder = adoptionRepairOrder(RepairOrderStatus::Approved, 'OPEN1');
    adoptionRepairOrder(RepairOrderStatus::InProgress, 'NONE1');

    $engagedInspection = app(EnsureInspectionAction::class)->execute($engagedOrder, $technician);
    app(EnsureInspectionAction::class)->execute($openedOnlyOrder, $technician);

    $brakeItem = InspectionItem::query()->create([
        'inspection_id' => $engagedInspection->id,
        'category' => 'brakes',
        'label' => 'Front brake pads',
        'observed_state' => InspectionObservedState::Measure->value,
        'notes' => '[Maintenance] RF pad measured.',
        'position' => 0,
    ]);

    InspectionItemMeasurement::query()->create([
        'inspection_item_id' => $brakeItem->id,
        'name' => 'Pad thickness',
        'value' => '4',
        'unit' => 'mm',
    ]);

    InspectionItem::query()->create([
        'inspection_id' => $engagedInspection->id,
        'category' => 'battery',
        'label' => 'Battery',
        'observed_state' => InspectionObservedState::NotChecked->value,
        'notes' => '[Diagnostic] CCA looks weak.',
        'position' => 1,
    ]);

    $report = app(InspectionAdoptionReport::class)->snapshot();

    expect($report['open_repair_orders']['total'])->toBe(3)
        ->and($report['open_repair_orders']['with_inspection'])->toBe(2)
        ->and($report['open_repair_orders']['without_inspection'])->toBe(1)
        ->and($report['open_repair_orders']['engaged'])->toBe(1)
        ->and($report['averages']['recorded_items'])->toBe(1.0)
        ->and($report['averages']['measurements'])->toBe(0.5)
        ->and($report['friction_signals']['notes_only_items'])->toBe(1)
        ->and($report['friction_signals']['items_with_measurements'])->toBe(1)
        ->and($report['categories']['most_used'][0]['category'])->toBe('Brakes')
        ->and($report['categories']['most_used'][0]['recorded_items'])->toBe(1);
});

test('inspection adoption report command runs', function () {
    $this->artisan('ark:inspection-adoption')
        ->assertSuccessful()
        ->expectsOutputToContain('Inspection adoption audit');
});

test('finding intent persists in notes prefix', function () {
    expect(InspectionFindingIntent::tryFromNotes('[Safety] Metal contact'))
        ->toBe(InspectionFindingIntent::Safety)
        ->and(InspectionFindingIntent::stripNotesPrefix('[Safety] Metal contact'))
        ->toBe('Metal contact');
});

function adoptionRepairOrder(RepairOrderStatus $status, string $plate): RepairOrder
{
    $customer = Customer::query()->create([
        'first_name' => 'Adoption',
        'last_name' => 'Customer',
        'phone' => '719555'.random_int(1000, 9999),
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'plate' => $plate,
        'year' => 2019,
        'make' => 'Honda',
        'model' => 'Civic',
        'vin' => strtoupper(substr(md5($plate), 0, 17)),
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => $status,
        'concern_summary' => 'Adoption audit fixture.',
    ]);

    RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'General inspection',
        'disposition' => 'draft',
        'position' => 0,
    ]);

    return $repairOrder->fresh();
}
