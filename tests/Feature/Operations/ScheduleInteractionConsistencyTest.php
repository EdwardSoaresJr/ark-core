<?php

use App\Ark\Operations\Appointments\Appointment;
use App\Ark\Operations\Appointments\AppointmentStatus;
use App\Ark\Operations\Appointments\SchedulingHours;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Operations\Workstations\WorkstationPresence;
use App\Ark\Runtime\Authorization\ArkRole;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    ShopSettings::current()->update([
        'appointments_enabled' => true,
        'shop_timezone' => 'America/Denver',
        'scheduling_hours' => SchedulingHours::defaultWeekly(),
    ]);
    ShopSettings::forgetCurrent();
    $this->seed(ArkAuthorizationSeeder::class);
});

function seedCanonicalAppointment(object $advisor): array
{
    $customer = Customer::query()->create([
        'first_name' => 'Canonical',
        'last_name' => 'Appt',
        'phone' => '5550199001',
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2020,
        'make' => 'Toyota',
        'model' => 'Camry',
    ]);
    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'repair_order_id' => 1888,
        'concern_summary' => 'Brake noise',
    ]);
    $appointment = Appointment::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'repair_order_id' => $repairOrder->id,
        'created_by_user_id' => $advisor->id,
        'advisor_user_id' => $advisor->id,
        'starts_at' => ShopDisplayTimezone::parseLocal('2026-09-15 10:00')->utc(),
        'ends_at' => ShopDisplayTimezone::parseLocal('2026-09-15 11:00')->utc(),
        'concern' => 'Brake noise at low speed',
        'status' => AppointmentStatus::Scheduled,
    ]);

    return compact('customer', 'vehicle', 'repairOrder', 'appointment');
}

test('month week and day share the canonical appointment popover surface', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-09-15 08:00:00', 'America/Denver'));
    $advisor = actingAsLearnCurrentAdvisor();
    seedCanonicalAppointment($advisor);

    $session = [WorkstationPresence::SESSION_BIND_DISMISSED => true];
    $canonical = [
        'ops-cal-month__popover',
        'RO #1888',
        'Canonical Appt',
        '2020 Toyota Camry',
        'Brake noise at low speed',
        'Open RO',
        'Edit appointment',
        'arkScheduleAppointmentPopover',
        'ops-cal-appt-event--open',
    ];

    $month = $this->actingAs($advisor)->withSession($session)
        ->get(route('operations.appointments.index', ['day' => '2026-09-15', 'view' => 'month']))
        ->assertOk();
    foreach ($canonical as $needle) {
        $month->assertSee($needle, false);
    }
    $month->assertDontSee('ops-cal-appt-assign', false);

    $weekDay = $this->actingAs($advisor)->withSession($session)
        ->get(route('operations.appointments.index', ['day' => '2026-09-15', 'view' => 'week']))
        ->assertOk();
    foreach ($canonical as $needle) {
        $weekDay->assertSee($needle, false);
    }
    $weekDay->assertDontSee('Save assignment', false);

    $weekTech = $this->actingAs($advisor)->withSession($session)
        ->get(route('operations.appointments.index', [
            'day' => '2026-09-15',
            'view' => 'week',
            'allocate' => 'technician',
        ]))
        ->assertOk();
    foreach ($canonical as $needle) {
        $weekTech->assertSee($needle, false);
    }
    $weekTech->assertSee('ops-cal-appt-assign', false)
        ->assertSee('Save assignment', false)
        ->assertSee('Assignment', false);

    $day = $this->actingAs($advisor)->withSession($session)
        ->get(route('operations.appointments.index', ['day' => '2026-09-15', 'view' => 'day']))
        ->assertOk();
    foreach ($canonical as $needle) {
        $day->assertSee($needle, false);
    }
    $day->assertDontSee('ops-cal-card__detail', false)
        ->assertDontSee('Save assignment', false);

    Carbon::setTestNow();
});

test('week allocation still assigns through the canonical popover', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-09-15 08:00:00', 'America/Denver'));
    $advisor = actingAsLearnCurrentAdvisor();
    $technician = actingAsLearnCurrentStaff(ArkRole::Technician);
    ['appointment' => $appointment] = seedCanonicalAppointment($advisor);

    $this->actingAs($advisor)
        ->patch(route('operations.appointments.assign', $appointment), [
            'technician_user_id' => $technician->id,
            'day' => '2026-09-15',
            'view' => 'week',
            'allocate' => 'technician',
        ])
        ->assertRedirect();

    expect((int) $appointment->fresh()->technician_user_id)->toBe((int) $technician->id);

    Carbon::setTestNow();
});
