<?php

use App\Ark\Operations\Appointments\Appointment;
use App\Ark\Operations\Appointments\AppointmentScheduleRowPresenter;
use App\Ark\Operations\Appointments\AppointmentStatus;
use App\Ark\Operations\Appointments\SchedulingHours;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Operations\Workstations\WorkstationPresence;
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

test('schedule row presenter exposes shop RO number when linked', function (): void {
    $advisor = actingAsLearnCurrentAdvisor();
    $customer = Customer::query()->create([
        'first_name' => 'John',
        'last_name' => 'Smith',
        'phone' => '5550100100',
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2018,
        'make' => 'Ford',
        'model' => 'F-150',
    ]);
    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'repair_order_id' => 1742,
        'concern_summary' => 'Check engine light',
    ]);
    $appointment = Appointment::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'repair_order_id' => $repairOrder->id,
        'created_by_user_id' => $advisor->id,
        'advisor_user_id' => $advisor->id,
        'starts_at' => Carbon::parse('2026-09-15 15:00:00', 'UTC'),
        'ends_at' => Carbon::parse('2026-09-15 16:30:00', 'UTC'),
        'concern' => 'Check engine light / loss of power',
        'status' => AppointmentStatus::Scheduled,
    ]);

    $row = app(AppointmentScheduleRowPresenter::class)->present($appointment->fresh(['vehicle', 'customer', 'repairOrder', 'advisor', 'technician', 'workstation']), $advisor);

    expect($row['repair_order_number'])->toBe('1742')
        ->and($row['repair_order_url'])->toBe(route('operations.repair-orders.show', $repairOrder))
        ->and($row['customer_name'])->toBe('John Smith')
        ->and($row['vehicle_label'])->toBe('2018 Ford F-150');
});

test('month board keeps appointments in place with RO hierarchy and open actions', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-09-15 08:00:00', 'America/Denver'));
    $advisor = actingAsLearnCurrentAdvisor();

    $customer = Customer::query()->create([
        'first_name' => 'John',
        'last_name' => 'Smith',
        'phone' => '5550100100',
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2018,
        'make' => 'Ford',
        'model' => 'F-150',
    ]);
    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'repair_order_id' => 1742,
        'concern_summary' => 'Check engine light',
    ]);
    Appointment::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'repair_order_id' => $repairOrder->id,
        'created_by_user_id' => $advisor->id,
        'advisor_user_id' => $advisor->id,
        'starts_at' => ShopDisplayTimezone::parseLocal('2026-09-15 09:00')->utc(),
        'ends_at' => ShopDisplayTimezone::parseLocal('2026-09-15 10:30')->utc(),
        'concern' => 'Check engine light / loss of power',
        'status' => AppointmentStatus::Scheduled,
    ]);

    $orphanCustomer = Customer::query()->create([
        'first_name' => 'No',
        'last_name' => 'RoYet',
        'phone' => '5550100200',
    ]);
    Appointment::query()->create([
        'customer_id' => $orphanCustomer->id,
        'created_by_user_id' => $advisor->id,
        'advisor_user_id' => $advisor->id,
        'starts_at' => ShopDisplayTimezone::parseLocal('2026-09-15 11:00')->utc(),
        'ends_at' => ShopDisplayTimezone::parseLocal('2026-09-15 11:30')->utc(),
        'concern' => 'Oil change request',
        'status' => AppointmentStatus::Scheduled,
    ]);

    $response = $this->actingAs($advisor)
        ->withSession([WorkstationPresence::SESSION_BIND_DISMISSED => true])
        ->get(route('operations.appointments.index', [
            'day' => '2026-09-15',
            'view' => 'month',
        ]))
        ->assertOk()
        ->assertSee('ops-cal-month', false)
        ->assertSee('ops-cal-month__event-btn', false)
        ->assertSee('ops-cal-month__popover', false)
        ->assertSee('#1742 · John Smith', false)
        ->assertSee('2018 Ford F-150', false)
        ->assertSee('RO #1742', false)
        ->assertSee('Open RO', false)
        ->assertSee('Edit appointment', false)
        ->assertSee('No RO yet', false)
        ->assertSee('No RoYet', false)
        ->assertSee('Check engine light / loss of power', false)
        ->assertSee(route('operations.repair-orders.show', $repairOrder), false)
        ->assertSee('/app/appointments?day=2026-09-15&amp;view=day', false);

    $html = $response->getContent();
    expect($html)->not->toMatch('/<a[^>]*class="[^"]*ops-cal-month__day[^"]*"/')
        ->and($html)->toContain('ops-cal-month__day')
        ->and($html)->toContain('openAppointmentId')
        ->and($html)->toContain('arkScheduleAppointmentPopover')
        ->and($html)->toContain('ops-cal-month__popover--above')
        ->and($html)->not->toContain('x-data="{ open: false }"')
        ->and(substr_count($html, 'toggle('))->toBeGreaterThanOrEqual(2);

    Carbon::setTestNow();
});
