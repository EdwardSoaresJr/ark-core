<?php

use App\Ark\Operations\Appointments\Appointment;
use App\Ark\Operations\Appointments\AppointmentStatus;
use App\Ark\Operations\Appointments\SchedulingHours;
use App\Ark\Operations\Appointments\SchedulingWorkspaceProjection;
use App\Ark\Operations\Appointments\UpcomingScheduleProjection;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Operations\Workstations\Workstation;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    ShopSettings::current()->update([
        'appointments_enabled' => true,
        'shop_timezone' => 'America/Denver',
        'scheduling_hours' => SchedulingHours::defaultWeekly(),
    ]);
    ShopSettings::forgetCurrent();
    $this->seed(ArkAuthorizationSeeder::class);
});

function appointmentTestBay(string $name = 'Bay 1'): Workstation
{
    return Workstation::query()->create([
        'shop_settings_id' => ShopSettings::current()->id,
        'name' => $name,
        'is_active' => true,
        'accepts_scheduled_work' => true,
    ]);
}

test('advisor can schedule an appointment', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-10 08:00:00'));
    $advisor = actingAsLearnCurrentAdvisor();
    $bay = appointmentTestBay();

    $customer = Customer::query()->create([
        'first_name' => 'Hunter',
        'last_name' => 'Bell',
        'phone' => '555-0100',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2019,
        'make' => 'Ford',
        'model' => 'F-150',
    ]);

    $this->actingAs($advisor)
        ->post(route('operations.appointments.store'), [
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'advisor_user_id' => $advisor->id,
            'workstation_id' => $bay->id,
            'starts_at' => '2026-06-10T08:00',
            'ends_at' => '2026-06-10T09:00',
            'concern' => 'Check engine light',
            'notes' => 'Customer will wait',
        ])
        ->assertRedirect();

    $appointment = Appointment::query()->sole();

    expect($appointment->status)->toBe(AppointmentStatus::Scheduled)
        ->and($appointment->customer_id)->toBe($customer->id)
        ->and($appointment->workstation_id)->toBe($bay->id);

    Carbon::setTestNow();
});

test('scheduling without a bay or technician is allowed', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-10 08:00:00'));
    $advisor = actingAsLearnCurrentAdvisor();
    appointmentTestBay();

    $customer = Customer::query()->create([
        'first_name' => 'Need',
        'last_name' => 'Bay',
        'phone' => '555-0110',
    ]);

    $this->actingAs($advisor)
        ->post(route('operations.appointments.store'), [
            'customer_id' => $customer->id,
            'advisor_user_id' => $advisor->id,
            'starts_at' => '2026-06-10T08:00',
            'ends_at' => '2026-06-10T09:00',
            'concern' => 'No station',
            'estimated_labor_hours' => 1,
        ])
        ->assertRedirect();

    $appointment = Appointment::query()->sole();

    expect($appointment->workstation_id)->toBeNull()
        ->and($appointment->technician_user_id)->toBeNull();

    Carbon::setTestNow();
});

test('advisor can schedule a customer without a vehicle', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-10 08:00:00'));
    $advisor = actingAsLearnCurrentAdvisor();

    $customer = Customer::query()->create([
        'first_name' => 'Later',
        'last_name' => 'Car',
        'phone' => '555-0111',
    ]);

    Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2020,
        'make' => 'Toyota',
        'model' => 'Camry',
    ]);

    $this->actingAs($advisor)
        ->get(route('operations.schedule', ['customer' => $customer->id]))
        ->assertOk()
        ->assertSee('Vehicle not set yet', false)
        ->assertSee('leave unset until they arrive', false);

    $this->actingAs($advisor)
        ->post(route('operations.appointments.store'), [
            'customer_id' => $customer->id,
            'vehicle_id' => '',
            'advisor_user_id' => $advisor->id,
            'starts_at' => '2026-06-10T08:00',
            'ends_at' => '2026-06-10T09:00',
            'concern' => 'Noise when cold',
            'estimated_labor_hours' => 1,
        ])
        ->assertRedirect();

    $appointment = Appointment::query()->sole();

    expect($appointment->customer_id)->toBe($customer->id)
        ->and($appointment->vehicle_id)->toBeNull();

    $this->actingAs($advisor)
        ->get(route('operations.appointments.show', $appointment))
        ->assertOk()
        ->assertSee('Vehicle not set yet', false);

    Carbon::setTestNow();
});

test('schedule search can create a customer and continue without a vehicle', function () {
    $advisor = actingAsLearnCurrentAdvisor();

    $this->actingAs($advisor)
        ->get(route('operations.schedule', ['q' => '555-0199']))
        ->assertOk()
        ->assertSee('Add &amp; schedule', false)
        ->assertSee('vehicle can wait until they arrive', false);

    $response = $this->actingAs($advisor)
        ->post(route('operations.customers.store'), [
            'first_name' => 'Sam',
            'last_name' => 'Schedule',
            'phone' => '555-0188',
            'return_to' => 'schedule',
        ]);

    $customer = Customer::query()->latest('id')->firstOrFail();

    expect($customer->first_name)->toBe('Sam')
        ->and($customer->vehicles)->toHaveCount(0);

    $response->assertRedirect(route('operations.schedule', ['customer' => $customer->id]));
});

test('schedule day defaults to agenda lanes', function () {
    $advisor = actingAsLearnCurrentAdvisor();

    $this->actingAs($advisor)
        ->get(route('operations.appointments.index', ['day' => '2026-06-10', 'view' => 'day']))
        ->assertOk()
        ->assertSee('Agenda', false);

    $workspace = app(SchedulingWorkspaceProjection::class)
        ->resolve(Carbon::parse('2026-06-10'), 'day');

    expect($workspace['lanes'])->toBe('agenda');
});

test('advisor can schedule an appointment with day time and suggested length', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-10 08:00:00'));
    $advisor = actingAsLearnCurrentAdvisor();
    $bay = appointmentTestBay();

    $customer = Customer::query()->create([
        'first_name' => 'Length',
        'last_name' => 'Form',
        'phone' => '555-0109',
    ]);

    $this->actingAs($advisor)
        ->post(route('operations.appointments.store'), [
            'customer_id' => $customer->id,
            'advisor_user_id' => $advisor->id,
            'workstation_id' => $bay->id,
            'starts_date' => '2026-06-10',
            'starts_time' => '08:00',
            'duration_minutes' => 90,
            'concern' => 'Diag',
        ])
        ->assertRedirect();

    $appointment = Appointment::query()->sole();
    $starts = ShopDisplayTimezone::present($appointment->starts_at);
    $ends = ShopDisplayTimezone::present($appointment->ends_at);

    expect($starts->format('Y-m-d H:i'))->toBe('2026-06-10 08:00')
        ->and($ends->format('Y-m-d H:i'))->toBe('2026-06-10 09:30')
        ->and((int) $starts->diffInMinutes($ends))->toBe(90);

    Carbon::setTestNow();
});

test('work surface shows schedule under tasks when appointments are enabled', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-10 07:30:00'));
    $advisor = actingAsLearnCurrentAdvisor();

    $customer = Customer::query()->create([
        'first_name' => 'Michael',
        'last_name' => 'Higashi',
        'phone' => '555-0101',
    ]);

    Appointment::query()->create([
        'customer_id' => $customer->id,
        'created_by_user_id' => $advisor->id,
        'advisor_user_id' => $advisor->id,
        'starts_at' => ShopDisplayTimezone::parseLocal('2026-06-10 10:00')->utc(),
        'ends_at' => ShopDisplayTimezone::parseLocal('2026-06-10 11:00')->utc(),
        'concern' => 'Oil change',
        'status' => AppointmentStatus::Confirmed,
    ]);

    $this->actingAs($advisor)
        ->get(route('operations.work.queue', 'tasks'))
        ->assertOk()
        ->assertSee('Schedule', false)
        ->assertSee('Michael Higashi')
        ->assertSee('Oil change')
        ->assertSee('Today', false)
        ->assertSee('Full schedule', false)
        ->assertDontSee("Today's Appointments", false);

    $projection = app(UpcomingScheduleProjection::class)->resolve(viewer: $advisor);

    expect($projection['total_count'])->toBe(1)
        ->and($projection['today'][0]['customer_name'])->toBe('Michael Higashi')
        ->and($projection['today'][0]['time_label'])->not->toBe('');

    Carbon::setTestNow();
});

test('appointment status can be marked canceled', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $customer = Customer::query()->create([
        'first_name' => 'Maricruz',
        'last_name' => 'Olivas',
        'phone' => '555-0102',
    ]);

    $appointment = Appointment::query()->create([
        'customer_id' => $customer->id,
        'created_by_user_id' => $advisor->id,
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDay()->addHour(),
        'concern' => 'Brakes',
        'status' => AppointmentStatus::Scheduled,
    ]);

    $this->actingAs($advisor)
        ->patch(route('operations.appointments.status', $appointment), [
            'status' => AppointmentStatus::Canceled->value,
        ])
        ->assertRedirect();

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::Canceled)
        ->and($appointment->fresh()->canceled_at)->not->toBeNull();
});

test('appointments index shows scheduling workspace day lanes', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-10 07:30:00'));
    $advisor = actingAsLearnCurrentAdvisor();

    $customer = Customer::query()->create([
        'first_name' => 'Week',
        'last_name' => 'View',
        'phone' => '555-0199',
    ]);

    Appointment::query()->create([
        'customer_id' => $customer->id,
        'created_by_user_id' => $advisor->id,
        'advisor_user_id' => $advisor->id,
        'starts_at' => ShopDisplayTimezone::parseLocal('2026-06-10 14:00')->utc(),
        'ends_at' => ShopDisplayTimezone::parseLocal('2026-06-10 15:00')->utc(),
        'concern' => 'Alignment check',
        'status' => AppointmentStatus::Confirmed,
    ]);

    $this->actingAs($advisor)
        ->get(route('operations.appointments.index', ['day' => '2026-06-10']))
        ->assertOk()
        ->assertSee('Schedule', false)
        ->assertSee('ops-cal-view-switch', false)
        ->assertSee('ops-cal-day', false)
        ->assertSee('ops-cal-card', false)
        ->assertSee('Week View', false);

    $this->actingAs($advisor)
        ->get(route('operations.appointments.index', ['day' => '2026-06-10', 'view' => 'week']))
        ->assertOk()
        ->assertSee('ops-cal-week--board', false)
        ->assertSee('Week View', false)
        ->assertSee('?edit=1', false);

    $this->actingAs($advisor)
        ->get(route('operations.appointments.index', ['day' => '2026-06-10', 'view' => 'day']))
        ->assertOk()
        ->assertSee('ops-cal-day', false)
        ->assertSee('ops-cal-card', false)
        ->assertDontSee('data-ops-cal-drag', false)
        ->assertDontSee('Resize appointment', false);

    Carbon::setTestNow();
});

test('appointment show offers Call and Text when customer has a phone', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $customer = Customer::query()->create([
        'first_name' => 'Comms',
        'last_name' => 'Ready',
        'phone' => '555-0177',
    ]);

    $appointment = Appointment::query()->create([
        'customer_id' => $customer->id,
        'created_by_user_id' => $advisor->id,
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDay()->addHour(),
        'concern' => 'Noise on cold start',
        'status' => AppointmentStatus::Scheduled,
    ]);

    $hubTextUrl = route('operations.customers.show', $customer).'?compose=text#customer-communication';

    $this->actingAs($advisor)
        ->get(route('operations.appointments.show', $appointment))
        ->assertOk()
        ->assertSee('Call', false)
        ->assertSee('Text', false)
        ->assertSee('tel:5550177', false)
        ->assertSee($hubTextUrl, false)
        ->assertDontSee('SendOutboundMessage', false);
});

test('appointment show omits Call and Text when customer has no phone', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $customer = Customer::query()->create([
        'first_name' => 'No',
        'last_name' => 'Phone',
        'phone' => null,
    ]);

    $appointment = Appointment::query()->create([
        'customer_id' => $customer->id,
        'created_by_user_id' => $advisor->id,
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDay()->addHour(),
        'concern' => 'Walk-in follow-up',
        'status' => AppointmentStatus::Scheduled,
    ]);

    $this->actingAs($advisor)
        ->get(route('operations.appointments.show', $appointment))
        ->assertOk()
        ->assertDontSee('>Call<', false)
        ->assertDontSee('compose=text#customer-communication', false);
});

test('schedule day cards offer Call and Text when customer has a phone', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-10 07:30:00'));
    $advisor = actingAsLearnCurrentAdvisor();

    $customer = Customer::query()->create([
        'first_name' => 'Row',
        'last_name' => 'Comms',
        'phone' => '555-0188',
    ]);

    Appointment::query()->create([
        'customer_id' => $customer->id,
        'created_by_user_id' => $advisor->id,
        'advisor_user_id' => $advisor->id,
        'starts_at' => ShopDisplayTimezone::parseLocal('2026-06-10 14:00')->utc(),
        'ends_at' => ShopDisplayTimezone::parseLocal('2026-06-10 15:00')->utc(),
        'concern' => 'Battery test',
        'status' => AppointmentStatus::Confirmed,
    ]);

    $hubTextUrl = route('operations.customers.show', $customer).'?compose=text#customer-communication';

    $this->actingAs($advisor)
        ->get(route('operations.appointments.index', ['day' => '2026-06-10', 'view' => 'day']))
        ->assertOk()
        ->assertSee('Row Comms', false)
        ->assertSee('ops-cal-card__comms', false)
        ->assertSee('tel:5550188', false)
        ->assertSee($hubTextUrl, false)
        ->assertDontSee('SendOutboundMessage', false);

    Carbon::setTestNow();
});

test('schedule day cards omit Call and Text when customer has no phone', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-10 07:30:00'));
    $advisor = actingAsLearnCurrentAdvisor();

    $customer = Customer::query()->create([
        'first_name' => 'Silent',
        'last_name' => 'Guest',
        'phone' => null,
    ]);

    Appointment::query()->create([
        'customer_id' => $customer->id,
        'created_by_user_id' => $advisor->id,
        'advisor_user_id' => $advisor->id,
        'starts_at' => ShopDisplayTimezone::parseLocal('2026-06-10 11:00')->utc(),
        'ends_at' => ShopDisplayTimezone::parseLocal('2026-06-10 12:00')->utc(),
        'concern' => 'Inspection',
        'status' => AppointmentStatus::Confirmed,
    ]);

    $this->actingAs($advisor)
        ->get(route('operations.appointments.index', ['day' => '2026-06-10', 'view' => 'day']))
        ->assertOk()
        ->assertSee('Silent Guest', false)
        ->assertDontSee('ops-cal-card__comms', false)
        ->assertDontSee('compose=text#customer-communication', false);

    Carbon::setTestNow();
});

test('appointment show opens reschedule editor when edit query is set', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $customer = Customer::query()->create([
        'first_name' => 'Edit',
        'last_name' => 'Open',
        'phone' => '555-0166',
    ]);

    $appointment = Appointment::query()->create([
        'customer_id' => $customer->id,
        'created_by_user_id' => $advisor->id,
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDay()->addHour(),
        'concern' => 'Reschedule path',
        'status' => AppointmentStatus::Scheduled,
    ]);

    $html = $this->actingAs($advisor)
        ->get(route('operations.appointments.show', ['appointment' => $appointment, 'edit' => 1]))
        ->assertOk()
        ->assertSee('Reschedule or edit', false)
        ->getContent();

    expect($html)->toContain('data-appointment-editor')
        ->and($html)->toMatch('/<details[^>]*\sopen[\s>]/i')
        ->and($html)->toContain('name="starts_time"')
        ->and($html)->toContain('name="duration_minutes"')
        ->and($html)->not->toContain('name="ends_time"')
        ->and($html)->not->toContain('type="datetime-local"');
});

test('advisor can open schedule create from a repair order', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $customer = Customer::query()->create([
        'first_name' => 'Ro',
        'last_name' => 'Link',
        'phone' => '555-0155',
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
        'repair_order_id' => 8801,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Comeback noise',
    ]);

    $this->actingAs($advisor)
        ->get(route('operations.schedule', [
            'repair_order' => $repairOrder->id,
        ]))
        ->assertOk()
        ->assertSee('Comeback noise', false)
        ->assertSee('labor hours — used for daily shop capacity', false)
        ->assertDontSee('name="workstation_id"', false)
        ->assertDontSee('name="technician_user_id"', false)
        ->assertSee('name="starts_time"', false)
        ->assertSee('name="duration_minutes"', false)
        ->assertDontSee('name="ends_time"', false)
        ->assertSee('name="repair_order_id"', false)
        ->assertDontSee('type="datetime-local"', false)
        ->assertDontSee('Work station is required', false);

    $this->actingAs($advisor)
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Schedule Follow-up', false)
        ->assertSee('/app/schedule?repair_order='.$repairOrder->id, false);
});

test('appointment can be rescheduled via patch endpoint', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-10 08:00:00'));
    $advisor = actingAsLearnCurrentAdvisor();
    $bay = appointmentTestBay();
    $customer = Customer::query()->create([
        'first_name' => 'Move',
        'last_name' => 'Me',
        'phone' => '555-0188',
    ]);

    $appointment = Appointment::query()->create([
        'customer_id' => $customer->id,
        'created_by_user_id' => $advisor->id,
        'workstation_id' => $bay->id,
        'starts_at' => ShopDisplayTimezone::parseLocal('2026-06-10 10:00')->utc(),
        'ends_at' => ShopDisplayTimezone::parseLocal('2026-06-10 11:00')->utc(),
        'concern' => 'Move test',
        'status' => AppointmentStatus::Scheduled,
    ]);

    $this->actingAs($advisor)
        ->patchJson(route('operations.appointments.reschedule', $appointment), [
            'starts_at' => '2026-06-10T11:00:00',
            'ends_at' => '2026-06-10T12:00:00',
        ])
        ->assertOk()
        ->assertJsonPath('appointment.concern', 'Move test');

    expect(ShopDisplayTimezone::present($appointment->fresh()->starts_at)->format('H:i'))->toBe('11:00');

    Carbon::setTestNow();
});
