<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use Illuminate\Support\Facades\DB;

test('concern advisor notes migrate into private note lines and clear the scope field', function () {
    $customer = Customer::query()->create([
        'first_name' => 'Ada',
        'last_name' => 'Advisor',
        'phone' => '555-0100',
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2019,
        'make' => 'Toyota',
        'model' => 'Camry',
    ]);
    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Battery weak',
    ]);
    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Battery weak',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'position' => 1,
    ]);

    DB::table('repair_order_concerns')
        ->where('id', $concern->id)
        ->update(['notes' => 'Verify CCA before selling battery.']);

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Note,
        'description' => 'Already a private note.',
        'quantity' => '1.00',
        'unit_price_cents' => 0,
        'is_private' => true,
    ]);

    $migration = require database_path('migrations/2026_07_23_220000_migrate_concern_advisor_notes_to_private_note_lines.php');
    $migration->up();

    $concern->refresh();

    expect($concern->notes)->toBeNull()
        ->and(
            RepairOrderLine::query()
                ->where('repair_order_concern_id', $concern->id)
                ->where('type', RepairOrderLineType::Note)
                ->where('description', 'Verify CCA before selling battery.')
                ->where('is_private', true)
                ->exists()
        )->toBeTrue()
        ->and(
            RepairOrderLine::query()
                ->where('repair_order_concern_id', $concern->id)
                ->where('type', RepairOrderLineType::Note)
                ->count()
        )->toBe(2);
});
