<?php

use App\Ark\Operations\Appointments\Appointment;
use App\Ark\Operations\Appointments\AppointmentStatus;
use App\Ark\Operations\Appointments\ArrivalPostureProjection;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    ShopSettings::current()->update([
        'appointments_enabled' => true,
        'shop_timezone' => 'America/Denver',
    ]);
    ShopSettings::forgetCurrent();
    $this->seed(ArkAuthorizationSeeder::class);
});

/**
 * @return array{0: RepairOrder, 1: Customer, 2: Vehicle}
 */
function arrivalPostureRepairOrder(RepairOrderStatus $status = RepairOrderStatus::Estimate): array
{
    $customer = Customer::query()->create([
        'first_name' => 'Arrival',
        'last_name' => 'Posture',
        'phone' => '7195554400',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2020,
        'make' => 'Honda',
        'model' => 'Civic',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => $status,
        'repair_order_id' => random_int(9400, 9499),
        'concern_summary' => 'Noise',
    ]);

    return [$repairOrder, $customer, $vehicle];
}

function arrivalPostureAppointment(RepairOrder $repairOrder, Customer $customer, Vehicle $vehicle, array $overrides = []): Appointment
{
    $creatorId = $overrides['created_by_user_id'] ?? actingAsLearnCurrentAdvisor()->id;

    return Appointment::query()->create(array_merge([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'repair_order_id' => $repairOrder->id,
        'created_by_user_id' => $creatorId,
        'starts_at' => ShopDisplayTimezone::parseLocal('2026-07-31 09:00:00')->utc(),
        'ends_at' => ShopDisplayTimezone::parseLocal('2026-07-31 10:00:00')->utc(),
        'status' => AppointmentStatus::Scheduled,
        'concern' => 'Noise',
    ], $overrides));
}

test('linked scheduled appointment projects Scheduled without changing RO status', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-30 15:00:00', 'UTC'));
    [$repairOrder, $customer, $vehicle] = arrivalPostureRepairOrder();
    $statusBefore = $repairOrder->status;

    arrivalPostureAppointment($repairOrder, $customer, $vehicle);

    $posture = app(ArrivalPostureProjection::class)->forRepairOrder($repairOrder);

    expect($posture->present)->toBeTrue()
        ->and($posture->posture)->toBe('scheduled')
        ->and($posture->headline)->toBe('Scheduled')
        ->and($posture->whenLabel)->toBe('Tomorrow · 9:00 AM')
        ->and($posture->subtitle)->toBe('Vehicle has not arrived.')
        ->and($posture->sourceStatus)->toBe(AppointmentStatus::Scheduled)
        ->and($repairOrder->fresh()->status->is($statusBefore))->toBeTrue();

    Carbon::setTestNow();
});

test('confirmed appointment collapses to Scheduled floor posture but retains source', function () {
    [$repairOrder, $customer, $vehicle] = arrivalPostureRepairOrder();
    arrivalPostureAppointment($repairOrder, $customer, $vehicle, [
        'status' => AppointmentStatus::Confirmed,
    ]);

    $posture = app(ArrivalPostureProjection::class)->forRepairOrder($repairOrder);

    expect($posture->posture)->toBe('scheduled')
        ->and($posture->headline)->toBe('Scheduled')
        ->and($posture->sourceStatus)->toBe(AppointmentStatus::Confirmed);
});

test('marking arrived stamps arrived_at once and never clears it', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-31 15:03:00', 'UTC'));
    $advisor = actingAsLearnCurrentAdvisor();
    [$repairOrder, $customer, $vehicle] = arrivalPostureRepairOrder(RepairOrderStatus::WaitingApproval);
    $appointment = arrivalPostureAppointment($repairOrder, $customer, $vehicle);
    $roStatus = $repairOrder->status;

    $this->actingAs($advisor)
        ->patch(route('operations.appointments.status', $appointment), [
            'status' => AppointmentStatus::Arrived->value,
        ])
        ->assertRedirect();

    $appointment->refresh();
    $firstArrivedAt = $appointment->arrived_at;

    expect($appointment->status)->toBe(AppointmentStatus::Arrived)
        ->and($firstArrivedAt)->not->toBeNull()
        ->and($repairOrder->fresh()->status->is($roStatus))->toBeTrue();

    Carbon::setTestNow(Carbon::parse('2026-07-31 16:00:00', 'UTC'));

    $this->actingAs($advisor)
        ->patch(route('operations.appointments.status', $appointment), [
            'status' => AppointmentStatus::Arrived->value,
        ])
        ->assertRedirect();

    expect($appointment->fresh()->arrived_at?->equalTo($firstArrivedAt))->toBeTrue();

    $this->actingAs($advisor)
        ->patch(route('operations.appointments.status', $appointment), [
            'status' => AppointmentStatus::Completed->value,
        ])
        ->assertRedirect();

    $appointment->refresh();

    expect($appointment->status)->toBe(AppointmentStatus::Completed)
        ->and($appointment->arrived_at?->equalTo($firstArrivedAt))->toBeTrue()
        ->and($repairOrder->fresh()->status->is($roStatus))->toBeTrue();

    $posture = app(ArrivalPostureProjection::class)->forRepairOrder($repairOrder->fresh());

    expect($posture->posture)->toBe('completed')
        ->and($posture->headline)->toBe('Completed');

    Carbon::setTestNow();
});

test('arrived posture uses arrived_at evidence in shop timezone', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-31 15:03:00', 'UTC')); // 9:03 AM Denver
    [$repairOrder, $customer, $vehicle] = arrivalPostureRepairOrder();
    arrivalPostureAppointment($repairOrder, $customer, $vehicle, [
        'status' => AppointmentStatus::Arrived,
        'arrived_at' => now(),
    ]);

    $posture = app(ArrivalPostureProjection::class)->forRepairOrder($repairOrder);

    expect($posture->posture)->toBe('arrived')
        ->and($posture->headline)->toBe('Arrived')
        ->and($posture->whenLabel)->toBe('Today · 9:03 AM')
        ->and($posture->subtitle)->toBeNull();

    Carbon::setTestNow();
});

test('historical arrived without arrived_at does not fake arrival time', function () {
    [$repairOrder, $customer, $vehicle] = arrivalPostureRepairOrder();
    arrivalPostureAppointment($repairOrder, $customer, $vehicle, [
        'status' => AppointmentStatus::Arrived,
        'arrived_at' => null,
    ]);

    $posture = app(ArrivalPostureProjection::class)->forRepairOrder($repairOrder);

    expect($posture->headline)->toBe('Arrived')
        ->and($posture->whenLabel)->toStartWith('Scheduled ');
});

test('projection never selects another repair orders appointment by customer vehicle or time', function () {
    [$roA, $customer, $vehicle] = arrivalPostureRepairOrder();
    [$roB] = arrivalPostureRepairOrder();

    $starts = ShopDisplayTimezone::parseLocal('2026-08-01 09:00:00')->utc();
    arrivalPostureAppointment($roA, $customer, $vehicle, [
        'starts_at' => $starts,
        'ends_at' => $starts->copy()->addHour(),
        'status' => AppointmentStatus::Scheduled,
    ]);

    // Same customer/vehicle/time, linked only to RO A — RO B must not inherit.
    $postureB = app(ArrivalPostureProjection::class)->forRepairOrder($roB);

    expect($postureB->present)->toBeFalse()
        ->and($postureB->headline)->toBeNull();

    $postureA = app(ArrivalPostureProjection::class)->forRepairOrder($roA);

    expect($postureA->present)->toBeTrue()
        ->and($postureA->posture)->toBe('scheduled');
});

test('pick rule prefers soonest active over older historical', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-30 12:00:00', 'UTC'));
    [$repairOrder, $customer, $vehicle] = arrivalPostureRepairOrder();

    arrivalPostureAppointment($repairOrder, $customer, $vehicle, [
        'starts_at' => ShopDisplayTimezone::parseLocal('2026-07-20 09:00:00')->utc(),
        'ends_at' => ShopDisplayTimezone::parseLocal('2026-07-20 10:00:00')->utc(),
        'status' => AppointmentStatus::Completed,
    ]);

    $soon = arrivalPostureAppointment($repairOrder, $customer, $vehicle, [
        'starts_at' => ShopDisplayTimezone::parseLocal('2026-08-02 11:00:00')->utc(),
        'ends_at' => ShopDisplayTimezone::parseLocal('2026-08-02 12:00:00')->utc(),
        'status' => AppointmentStatus::Scheduled,
    ]);

    $posture = app(ArrivalPostureProjection::class)->forRepairOrder($repairOrder);

    expect($posture->present)->toBeTrue()
        ->and($posture->posture)->toBe('scheduled')
        ->and($posture->appointmentUrl)->toBe(route('operations.appointments.show', $soon));

    Carbon::setTestNow();
});

test('ro show renders arrival posture and schedule follow-up when absent', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-30 15:00:00', 'UTC'));
    $advisor = actingAsLearnCurrentAdvisor();
    [$repairOrder, $customer, $vehicle] = arrivalPostureRepairOrder();

    $this->actingAs($advisor)
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Schedule Follow-up', false)
        ->assertDontSee('Vehicle has not arrived.', false);

    arrivalPostureAppointment($repairOrder, $customer, $vehicle);

    $this->actingAs($advisor)
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('ops-review-toolbar-section--visit-signals', false)
        ->assertSee('Appt', false)
        ->assertSee('Scheduled', false)
        ->assertSee('Tomorrow · 9:00 AM', false)
        ->assertSee('Vehicle has not arrived.', false);

    Carbon::setTestNow();
});
