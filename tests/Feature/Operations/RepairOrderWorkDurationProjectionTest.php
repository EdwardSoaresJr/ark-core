<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Events\OperationalEventRecorder;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\RepairOrders\RepairOrderWorkDurationProjection;
use App\Ark\Operations\Vehicles\Vehicle;
use Illuminate\Support\Carbon;


test('repair order work duration projection derives dispatch delay and repair cycle from lifecycle events', function (): void {
    $customer = Customer::query()->create([
        'first_name' => 'Test',
        'last_name' => 'Customer',
        'phone' => '5550100',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2012,
        'make' => 'Ram',
        'model' => '2500',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::ReadyPickup,
        'concern_summary' => 'Cooling concern',
    ]);

    $recorder = app(OperationalEventRecorder::class);

    Carbon::setTestNow(Carbon::parse('2026-06-12 08:00:00'));
    $recorder->record(
        OperationalEventName::RepairOrderLifecycleChanged,
        $repairOrder,
        payload: [
            'from_status' => RepairOrderStatus::Approved->value,
            'to_status' => RepairOrderStatus::ReadyForWork->value,
        ],
    );

    Carbon::setTestNow(Carbon::parse('2026-06-12 08:18:00'));
    $recorder->record(
        OperationalEventName::RepairOrderLifecycleChanged,
        $repairOrder,
        payload: [
            'from_status' => RepairOrderStatus::ReadyForWork->value,
            'to_status' => RepairOrderStatus::InProgress->value,
        ],
    );

    Carbon::setTestNow(Carbon::parse('2026-06-12 10:45:00'));
    $recorder->record(
        OperationalEventName::RepairOrderLifecycleChanged,
        $repairOrder,
        payload: [
            'from_status' => RepairOrderStatus::InProgress->value,
            'to_status' => RepairOrderStatus::ReadyPickup->value,
        ],
    );

    Carbon::setTestNow();

    $metrics = app(RepairOrderWorkDurationProjection::class)->for($repairOrder->fresh());

    expect($metrics)->toHaveCount(2)
        ->and($metrics[0]['key'])->toBe('dispatch_delay')
        ->and($metrics[0]['duration_label'])->toBe('18m')
        ->and($metrics[0]['status'])->toBe('complete')
        ->and($metrics[1]['key'])->toBe('repair_cycle')
        ->and($metrics[1]['duration_label'])->toBe('2h 27m')
        ->and($metrics[1]['status'])->toBe('complete');
});

test('repair order work duration projection leaves pending metrics without duration label', function (): void {
    $customer = Customer::query()->create([
        'first_name' => 'Test',
        'last_name' => 'Customer',
        'phone' => '5550101',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2012,
        'make' => 'Ram',
        'model' => '2500',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::ReadyForWork,
        'concern_summary' => 'Cooling concern',
    ]);

    app(OperationalEventRecorder::class)->record(
        OperationalEventName::RepairOrderLifecycleChanged,
        $repairOrder,
        payload: [
            'from_status' => RepairOrderStatus::Approved->value,
            'to_status' => RepairOrderStatus::ReadyForWork->value,
        ],
    );

    $metrics = app(RepairOrderWorkDurationProjection::class)->for($repairOrder->fresh());

    expect($metrics[0]['duration_label'])->toBeNull()
        ->and($metrics[0]['status'])->toBe('pending')
        ->and($metrics[1]['duration_label'])->toBeNull()
        ->and($metrics[1]['status'])->toBe('pending');
});
