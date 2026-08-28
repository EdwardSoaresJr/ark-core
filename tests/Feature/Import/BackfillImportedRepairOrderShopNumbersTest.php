<?php

use App\Ark\Import\ArrayLegacyArkSmsReader;
use App\Ark\Import\ImportedRepairOrderShopNumberBackfiller;
use App\Ark\Import\LegacyArkSmsImporter;
use App\Ark\Import\LegacyArkSmsValueMapper;
use App\Ark\Import\LegacyImportOptions;
use App\Ark\Import\LegacyImportReport;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;

function backfillLegacyReaderFixture(): ArrayLegacyArkSmsReader
{
    return new ArrayLegacyArkSmsReader([
        'customers' => [[
            'id' => 101,
            'first_name' => 'Legacy',
            'last_name' => 'Customer',
            'phone' => '(719) 555-0101',
            'email' => 'legacy@example.test',
            'created_at' => '2024-01-01 10:00:00',
            'updated_at' => '2024-06-01 10:00:00',
        ]],
        'vehicles' => [[
            'id' => 201,
            'customer_id' => 101,
            'plate' => 'LEG101',
            'year' => 2015,
            'make' => 'Subaru',
            'model' => 'Outback',
            'created_at' => '2024-01-02 10:00:00',
            'updated_at' => '2024-06-02 10:00:00',
        ]],
        'repair_orders' => [[
            'id' => 301,
            'shop_number' => 1301,
            'customer_id' => 101,
            'vehicle_id' => 201,
            'status' => 'invoiced',
            'concern_summary' => 'Brake noise at low speed.',
            'created_at' => '2024-06-03 09:00:00',
            'updated_at' => '2024-06-10 15:00:00',
        ]],
        'concerns' => [[
            'id' => 401,
            'repair_order_id' => 301,
            'summary' => 'Brake noise',
            'disposition' => 'approved',
            'position' => 0,
        ]],
        'lines' => [],
        'invoices' => [],
    ]);
}

test('backfill restores legacy shop repair order numbers when stamped with database id', function () {
    $reader = backfillLegacyReaderFixture();

    (new LegacyArkSmsImporter($reader, new LegacyArkSmsValueMapper))
        ->run(new LegacyImportOptions(dryRun: false), new LegacyImportReport);

    $repairOrder = RepairOrder::query()->where('repair_order_id', 1301)->firstOrFail();
    $databaseId = $repairOrder->id;

    $repairOrder->forceFill(['repair_order_id' => $databaseId])->saveQuietly();

    expect($repairOrder->refresh()->repair_order_id)->toBe($databaseId);

    $result = (new ImportedRepairOrderShopNumberBackfiller($reader))->run();

    expect($result['updated'])->toBeGreaterThanOrEqual(1)
        ->and($repairOrder->refresh()->repair_order_id)->toBe(1301)
        ->and($repairOrder->repair_order_id)->not->toBe($databaseId);
});

test('backfill assigns sequential shop numbers to native repair orders on imported customers', function () {
    $reader = backfillLegacyReaderFixture();

    (new LegacyArkSmsImporter($reader, new LegacyArkSmsValueMapper))
        ->run(new LegacyImportOptions(dryRun: false), new LegacyImportReport);

    $customer = Customer::query()->where('legacy_arksms_customer_id', 101)->firstOrFail();
    $vehicle = Vehicle::query()->where('legacy_arksms_vehicle_id', 201)->firstOrFail();

    $nativeRepairOrder = RepairOrder::withoutEvents(function () use ($customer, $vehicle): RepairOrder {
        $repairOrder = RepairOrder::query()->create([
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'status' => RepairOrderStatus::Draft,
            'concern_summary' => 'Native follow-up visit',
            'opened_at' => now(),
        ]);

        $repairOrder->forceFill(['repair_order_id' => $repairOrder->id])->saveQuietly();

        return $repairOrder->refresh();
    });

    $result = (new ImportedRepairOrderShopNumberBackfiller($reader))->run();

    $nativeRepairOrder->refresh();

    expect($result['native'])->toBeGreaterThanOrEqual(1)
        ->and($nativeRepairOrder->repair_order_id)->toBeGreaterThan(1301)
        ->and($nativeRepairOrder->repair_order_id)->not->toBe($nativeRepairOrder->id);
});

test('new repair orders continue shop number sequence after legacy backfill', function () {
    RepairOrder::query()->create([
        'repair_order_id' => 88041,
        'customer_id' => Customer::query()->create([
            'first_name' => 'Rosa',
            'last_name' => 'Garcia',
            'phone' => '555-0100',
        ])->id,
        'vehicle_id' => Vehicle::query()->create([
            'customer_id' => Customer::query()->latest('id')->value('id'),
            'year' => 2018,
            'make' => 'Honda',
            'model' => 'Accord',
        ])->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Sequence anchor',
    ]);

    $next = RepairOrder::query()->create([
        'customer_id' => Customer::query()->latest('id')->value('id'),
        'vehicle_id' => Vehicle::query()->latest('id')->value('id'),
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Next shop number',
    ]);

    expect($next->repair_order_id)->toBe(88042);
});
