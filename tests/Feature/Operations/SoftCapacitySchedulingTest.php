<?php

use App\Ark\Operations\Appointments\Appointment;
use App\Ark\Operations\Appointments\AppointmentCapacityBasis;
use App\Ark\Operations\Appointments\AppointmentCapacityEnforcement;
use App\Ark\Operations\Appointments\AppointmentStatus;
use App\Ark\Operations\Appointments\DayLens;
use App\Ark\Operations\Appointments\SchedulingCapacityCalculator;
use App\Ark\Operations\Appointments\SchedulingHours;
use App\Ark\Operations\Appointments\SchedulingWorkspaceProjection;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Workstations\Workstation;
use App\Ark\Runtime\Authorization\ArkRole;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    ShopSettings::current()->update([
        'appointments_enabled' => true,
        'shop_timezone' => 'America/Denver',
        'scheduling_hours' => SchedulingHours::defaultWeekly(),
        'appointment_capacity_basis' => AppointmentCapacityBasis::LimitingResource->value,
        'appointment_scheduling_target_percent' => 100,
        'appointment_capacity_enforcement' => AppointmentCapacityEnforcement::Warn->value,
    ]);
    ShopSettings::forgetCurrent();
    $this->seed(ArkAuthorizationSeeder::class);
});

test('capacity basis technicians uses technician open hours', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-10 08:00:00'));
    actingAsLearnCurrentStaff(ArkRole::Technician);
    ShopSettings::current()->update([
        'appointment_capacity_basis' => AppointmentCapacityBasis::Technicians->value,
    ]);
    ShopSettings::forgetCurrent();

    $snapshot = app(SchedulingCapacityCalculator::class)->forDay(Carbon::parse('2026-06-10'));

    // One technician × 9h shop day (08:00–17:00)
    expect($snapshot->available)->toBeTrue()
        ->and($snapshot->baseCapacityHours)->toBe(9.0)
        ->and($snapshot->basisUsed)->toBe('technicians');

    Carbon::setTestNow();
});

test('capacity basis bays uses bay count times shop hours', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-10 08:00:00'));
    ShopSettings::current()->update([
        'appointment_capacity_basis' => AppointmentCapacityBasis::Bays->value,
    ]);
    ShopSettings::forgetCurrent();

    Workstation::query()->create([
        'shop_settings_id' => ShopSettings::current()->id,
        'name' => 'Bay 1',
        'is_active' => true,
        'accepts_scheduled_work' => true,
    ]);
    Workstation::query()->create([
        'shop_settings_id' => ShopSettings::current()->id,
        'name' => 'Bay 2',
        'is_active' => true,
        'accepts_scheduled_work' => true,
    ]);

    $snapshot = app(SchedulingCapacityCalculator::class)->forDay(Carbon::parse('2026-06-10'));

    expect($snapshot->available)->toBeTrue()
        ->and($snapshot->baseCapacityHours)->toBe(18.0)
        ->and($snapshot->basisUsed)->toBe('bays');

    Carbon::setTestNow();
});

test('limiting resource chooses the lower capacity', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-10 08:00:00'));
    actingAsLearnCurrentStaff(ArkRole::Technician);
    ShopSettings::current()->update([
        'appointment_capacity_basis' => AppointmentCapacityBasis::LimitingResource->value,
    ]);
    ShopSettings::forgetCurrent();

    Workstation::query()->create([
        'shop_settings_id' => ShopSettings::current()->id,
        'name' => 'Bay 1',
        'is_active' => true,
        'accepts_scheduled_work' => true,
    ]);
    Workstation::query()->create([
        'shop_settings_id' => ShopSettings::current()->id,
        'name' => 'Bay 2',
        'is_active' => true,
        'accepts_scheduled_work' => true,
    ]);

    $snapshot = app(SchedulingCapacityCalculator::class)->forDay(Carbon::parse('2026-06-10'));

    // techs=9, bays=18 → limiting 9
    expect($snapshot->baseCapacityHours)->toBe(9.0)
        ->and($snapshot->basisUsed)->toBe('limiting_resource');

    Carbon::setTestNow();
});

test('no resource data returns capacity unavailable', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-10 08:00:00'));

    $snapshot = app(SchedulingCapacityCalculator::class)->forDay(Carbon::parse('2026-06-10'));

    expect($snapshot->available)->toBeFalse()
        ->and($snapshot->baseCapacityHours)->toBeNull();

    Carbon::setTestNow();
});

test('target percent scales bookable capacity', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-10 08:00:00'));
    actingAsLearnCurrentStaff(ArkRole::Technician);
    ShopSettings::current()->update([
        'appointment_capacity_basis' => AppointmentCapacityBasis::Technicians->value,
        'appointment_scheduling_target_percent' => 125,
    ]);
    ShopSettings::forgetCurrent();

    $snapshot = app(SchedulingCapacityCalculator::class)->forDay(Carbon::parse('2026-06-10'));

    expect($snapshot->baseCapacityHours)->toBe(9.0)
        ->and($snapshot->targetCapacityHours)->toBe(11.25);

    Carbon::setTestNow();
});

test('warn mode allows scheduling beyond target with warning', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-10 08:00:00'));
    $advisor = actingAsLearnCurrentAdvisor();
    actingAsLearnCurrentStaff(ArkRole::Technician);
    ShopSettings::current()->update([
        'appointment_capacity_basis' => AppointmentCapacityBasis::Technicians->value,
        'appointment_scheduling_target_percent' => 100,
        'appointment_capacity_enforcement' => AppointmentCapacityEnforcement::Warn->value,
    ]);
    ShopSettings::forgetCurrent();

    $customer = Customer::query()->create([
        'first_name' => 'Over',
        'last_name' => 'Pack',
        'phone' => '555-0801',
    ]);

    $this->actingAs($advisor)
        ->post(route('operations.appointments.store'), [
            'customer_id' => $customer->id,
            'advisor_user_id' => $advisor->id,
            'starts_at' => '2026-06-10T08:00',
            'ends_at' => '2026-06-10T09:00',
            'concern' => 'Heavy',
            'estimated_labor_hours' => 12,
        ])
        ->assertRedirect()
        ->assertSessionHas('schedule_warnings');

    expect(Appointment::query()->count())->toBe(1);

    Carbon::setTestNow();
});

test('block mode rejects scheduling beyond target', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-10 08:00:00'));
    $advisor = actingAsLearnCurrentAdvisor();
    actingAsLearnCurrentStaff(ArkRole::Technician);
    ShopSettings::current()->update([
        'appointment_capacity_basis' => AppointmentCapacityBasis::Technicians->value,
        'appointment_scheduling_target_percent' => 100,
        'appointment_capacity_enforcement' => AppointmentCapacityEnforcement::Block->value,
    ]);
    ShopSettings::forgetCurrent();

    $customer = Customer::query()->create([
        'first_name' => 'Blocked',
        'last_name' => 'Pack',
        'phone' => '555-0802',
    ]);

    $this->actingAs($advisor)
        ->post(route('operations.appointments.store'), [
            'customer_id' => $customer->id,
            'advisor_user_id' => $advisor->id,
            'starts_at' => '2026-06-10T08:00',
            'ends_at' => '2026-06-10T09:00',
            'concern' => 'Too much',
            'estimated_labor_hours' => 12,
        ])
        ->assertSessionHasErrors('estimated_labor_hours');

    expect(Appointment::query()->count())->toBe(0);

    Carbon::setTestNow();
});

test('canceled appointments do not consume capacity', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-10 08:00:00'));
    $advisor = actingAsLearnCurrentAdvisor();
    actingAsLearnCurrentStaff(ArkRole::Technician);
    ShopSettings::current()->update([
        'appointment_capacity_basis' => AppointmentCapacityBasis::Technicians->value,
    ]);
    ShopSettings::forgetCurrent();

    $customer = Customer::query()->create([
        'first_name' => 'Cancel',
        'last_name' => 'Load',
        'phone' => '555-0803',
    ]);

    Appointment::query()->create([
        'customer_id' => $customer->id,
        'created_by_user_id' => $advisor->id,
        'starts_at' => ShopDisplayTimezone::parseLocal('2026-06-10 08:00')->utc(),
        'ends_at' => ShopDisplayTimezone::parseLocal('2026-06-10 10:00')->utc(),
        'estimated_labor_hours' => 8,
        'concern' => 'Canceled load',
        'status' => AppointmentStatus::Canceled,
        'canceled_at' => now(),
    ]);

    $snapshot = app(SchedulingCapacityCalculator::class)->forDay(Carbon::parse('2026-06-10'));

    expect($snapshot->scheduledHours)->toBe(0.0);

    Carbon::setTestNow();
});

test('blank reserved labor inherits appointment length for capacity', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-10 08:00:00'));
    $advisor = actingAsLearnCurrentAdvisor();
    actingAsLearnCurrentStaff(ArkRole::Technician);
    ShopSettings::current()->update([
        'appointment_capacity_basis' => AppointmentCapacityBasis::Technicians->value,
    ]);
    ShopSettings::forgetCurrent();

    $customer = Customer::query()->create([
        'first_name' => 'Inherit',
        'last_name' => 'Length',
        'phone' => '555-0811',
    ]);

    $this->actingAs($advisor)
        ->post(route('operations.appointments.store'), [
            'customer_id' => $customer->id,
            'advisor_user_id' => $advisor->id,
            'starts_at' => '2026-06-10T09:00',
            'ends_at' => '2026-06-10T10:30',
            'concern' => 'Diagnostic',
        ])
        ->assertRedirect();

    $appointment = Appointment::query()->sole();

    expect($appointment->estimated_labor_hours)->toBeNull();

    $snapshot = app(SchedulingCapacityCalculator::class)->forDay(Carbon::parse('2026-06-10'));

    expect($snapshot->scheduledHours)->toBe(1.5);

    Carbon::setTestNow();
});

test('explicit reserved labor override is preserved and used for capacity', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-10 08:00:00'));
    $advisor = actingAsLearnCurrentAdvisor();
    actingAsLearnCurrentStaff(ArkRole::Technician);
    ShopSettings::current()->update([
        'appointment_capacity_basis' => AppointmentCapacityBasis::Technicians->value,
    ]);
    ShopSettings::forgetCurrent();

    $customer = Customer::query()->create([
        'first_name' => 'Override',
        'last_name' => 'Labor',
        'phone' => '555-0812',
    ]);

    $this->actingAs($advisor)
        ->post(route('operations.appointments.store'), [
            'customer_id' => $customer->id,
            'advisor_user_id' => $advisor->id,
            'starts_at' => '2026-06-10T09:00',
            'ends_at' => '2026-06-10T10:30',
            'concern' => 'Diagnostic with work',
            'estimated_labor_hours' => 2.5,
        ])
        ->assertRedirect();

    $appointment = Appointment::query()->sole();

    expect((float) $appointment->estimated_labor_hours)->toBe(2.5);

    $snapshot = app(SchedulingCapacityCalculator::class)->forDay(Carbon::parse('2026-06-10'));

    expect($snapshot->scheduledHours)->toBe(2.5);

    $this->actingAs($advisor)
        ->get(route('operations.appointments.show', $appointment))
        ->assertOk()
        ->assertSee('· Manual', false)
        ->assertSee('Reset to appointment length', false);

    Carbon::setTestNow();
});

test('resetting reserved labor clears override back to auto', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-10 08:00:00'));
    $advisor = actingAsLearnCurrentAdvisor();
    actingAsLearnCurrentStaff(ArkRole::Technician);
    ShopSettings::current()->update([
        'appointment_capacity_basis' => AppointmentCapacityBasis::Technicians->value,
    ]);
    ShopSettings::forgetCurrent();

    $customer = Customer::query()->create([
        'first_name' => 'Reset',
        'last_name' => 'Labor',
        'phone' => '555-0813',
    ]);

    $appointment = Appointment::query()->create([
        'customer_id' => $customer->id,
        'created_by_user_id' => $advisor->id,
        'advisor_user_id' => $advisor->id,
        'starts_at' => ShopDisplayTimezone::parseLocal('2026-06-10 09:00')->utc(),
        'ends_at' => ShopDisplayTimezone::parseLocal('2026-06-10 10:30')->utc(),
        'estimated_labor_hours' => 2.5,
        'concern' => 'Was overridden',
        'status' => AppointmentStatus::Scheduled,
    ]);

    $this->actingAs($advisor)
        ->patch(route('operations.appointments.update', $appointment), [
            'customer_id' => $customer->id,
            'advisor_user_id' => $advisor->id,
            'starts_at' => '2026-06-10T09:00',
            'ends_at' => '2026-06-10T10:30',
            'concern' => 'Was overridden',
            'estimated_labor_hours' => '',
        ])
        ->assertRedirect();

    $appointment->refresh();

    expect($appointment->estimated_labor_hours)->toBeNull();

    $snapshot = app(SchedulingCapacityCalculator::class)->forDay(Carbon::parse('2026-06-10'));

    expect($snapshot->scheduledHours)->toBe(1.5);

    Carbon::setTestNow();
});

test('unassigned appointments appear on the agenda scheduler', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-10 08:00:00'));
    $advisor = actingAsLearnCurrentAdvisor();

    $customer = Customer::query()->create([
        'first_name' => 'Open',
        'last_name' => 'Slot',
        'phone' => '555-0804',
    ]);

    Appointment::query()->create([
        'customer_id' => $customer->id,
        'created_by_user_id' => $advisor->id,
        'starts_at' => ShopDisplayTimezone::parseLocal('2026-06-10 10:00')->utc(),
        'ends_at' => ShopDisplayTimezone::parseLocal('2026-06-10 11:00')->utc(),
        'estimated_labor_hours' => 1,
        'concern' => 'Unassigned plan',
        'status' => AppointmentStatus::Scheduled,
    ]);

    $agenda = app(SchedulingWorkspaceProjection::class)->resolve(Carbon::parse('2026-06-10'), 'day', 'agenda');

    expect($agenda['total_count'])->toBe(1)
        ->and($agenda['lanes'])->toBe('agenda')
        ->and($agenda['lane_rows'])->toHaveCount(1)
        ->and($agenda['lane_rows'][0]['cards'])->toHaveCount(1)
        ->and($agenda['lane_rows'][0]['cards'][0]['column_index'])->toBe(0)
        ->and($agenda['lane_rows'][0]['cards'][0]['column_count'])->toBe(3);

    $this->actingAs($advisor)
        ->get(route('operations.appointments.index', ['day' => '2026-06-10', 'view' => 'day']))
        ->assertOk()
        ->assertDontSee('Technicians', false)
        ->assertDontSee('>Bays<', false)
        ->assertSee('Unassigned plan', false)
        ->assertSee('ops-cal-card__detail', false)
        ->assertSee('Open to reschedule', false);

    Carbon::setTestNow();
});

test('agenda packs overlapping appointments into side-by-side columns', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-10 08:00:00'));
    $advisor = actingAsLearnCurrentAdvisor();

    $customerA = Customer::query()->create(['first_name' => 'Gary', 'last_name' => 'One', 'phone' => '555-0810']);
    $customerB = Customer::query()->create(['first_name' => 'Pat', 'last_name' => 'Two', 'phone' => '555-0811']);
    $customerC = Customer::query()->create(['first_name' => 'Later', 'last_name' => 'Three', 'phone' => '555-0812']);

    Appointment::query()->create([
        'customer_id' => $customerA->id,
        'created_by_user_id' => $advisor->id,
        'starts_at' => ShopDisplayTimezone::parseLocal('2026-06-10 09:00')->utc(),
        'ends_at' => ShopDisplayTimezone::parseLocal('2026-06-10 11:00')->utc(),
        'estimated_labor_hours' => 2,
        'concern' => 'Overlap A',
        'status' => AppointmentStatus::Scheduled,
    ]);
    Appointment::query()->create([
        'customer_id' => $customerB->id,
        'created_by_user_id' => $advisor->id,
        'starts_at' => ShopDisplayTimezone::parseLocal('2026-06-10 09:30')->utc(),
        'ends_at' => ShopDisplayTimezone::parseLocal('2026-06-10 10:30')->utc(),
        'estimated_labor_hours' => 1,
        'concern' => 'Overlap B',
        'status' => AppointmentStatus::Scheduled,
    ]);
    Appointment::query()->create([
        'customer_id' => $customerC->id,
        'created_by_user_id' => $advisor->id,
        'starts_at' => ShopDisplayTimezone::parseLocal('2026-06-10 13:00')->utc(),
        'ends_at' => ShopDisplayTimezone::parseLocal('2026-06-10 14:00')->utc(),
        'estimated_labor_hours' => 1,
        'concern' => 'Afternoon alone',
        'status' => AppointmentStatus::Scheduled,
    ]);

    $agenda = app(SchedulingWorkspaceProjection::class)->resolve(Carbon::parse('2026-06-10'), 'day', 'agenda');
    $cards = collect($agenda['lane_rows'][0]['cards']);

    $overlapA = $cards->firstWhere('concern', 'Overlap A');
    $overlapB = $cards->firstWhere('concern', 'Overlap B');
    $afternoon = $cards->firstWhere('concern', 'Afternoon alone');

    expect($overlapA['column_index'])->not->toBe($overlapB['column_index'])
        ->and($overlapA['column_count'])->toBe(3)
        ->and($overlapB['column_count'])->toBe(3)
        ->and($afternoon['column_index'])->toBe(0)
        ->and($afternoon['column_count'])->toBe(3);

    Carbon::setTestNow();
});

test('bay and technician overlap warn but do not block save', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-10 08:00:00'));
    $advisor = actingAsLearnCurrentAdvisor();
    $technician = actingAsLearnCurrentStaff(ArkRole::Technician);
    $bay = Workstation::query()->create([
        'shop_settings_id' => ShopSettings::current()->id,
        'name' => 'Bay 1',
        'is_active' => true,
        'accepts_scheduled_work' => true,
    ]);

    $customerA = Customer::query()->create(['first_name' => 'A', 'last_name' => 'One', 'phone' => '555-0805']);
    $customerB = Customer::query()->create(['first_name' => 'B', 'last_name' => 'Two', 'phone' => '555-0806']);

    Appointment::query()->create([
        'customer_id' => $customerA->id,
        'technician_user_id' => $technician->id,
        'workstation_id' => $bay->id,
        'created_by_user_id' => $advisor->id,
        'starts_at' => ShopDisplayTimezone::parseLocal('2026-06-10 10:00')->utc(),
        'ends_at' => ShopDisplayTimezone::parseLocal('2026-06-10 11:00')->utc(),
        'estimated_labor_hours' => 1,
        'concern' => 'First',
        'status' => AppointmentStatus::Scheduled,
    ]);

    $this->actingAs($advisor)
        ->post(route('operations.appointments.store'), [
            'customer_id' => $customerB->id,
            'technician_user_id' => $technician->id,
            'workstation_id' => $bay->id,
            'starts_at' => '2026-06-10T10:30',
            'ends_at' => '2026-06-10T11:30',
            'concern' => 'Overlap ok',
            'estimated_labor_hours' => 1,
        ])
        ->assertRedirect()
        ->assertSessionHas('schedule_warnings')
        ->assertSessionDoesntHaveErrors();

    expect(Appointment::query()->count())->toBe(2);

    Carbon::setTestNow();
});

test('DayLens chips are projection-owned and filter the same appointments', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-10 08:00:00'));
    $advisor = actingAsLearnCurrentAdvisor();
    $technician = actingAsLearnCurrentStaff(ArkRole::Technician);
    $technician->forceFill(['name' => 'Edward Chen'])->save();

    $bay = Workstation::query()->create([
        'shop_settings_id' => ShopSettings::current()->id,
        'name' => 'Bay 3',
        'is_active' => true,
        'accepts_scheduled_work' => true,
    ]);

    $unassignedCustomer = Customer::query()->create(['first_name' => 'Open', 'last_name' => 'Lane', 'phone' => '555-0901']);
    $techCustomer = Customer::query()->create(['first_name' => 'Tech', 'last_name' => 'Job', 'phone' => '555-0902']);
    $bayCustomer = Customer::query()->create(['first_name' => 'Bay', 'last_name' => 'Job', 'phone' => '555-0903']);

    Appointment::query()->create([
        'customer_id' => $unassignedCustomer->id,
        'created_by_user_id' => $advisor->id,
        'starts_at' => ShopDisplayTimezone::parseLocal('2026-06-10 09:00')->utc(),
        'ends_at' => ShopDisplayTimezone::parseLocal('2026-06-10 10:00')->utc(),
        'estimated_labor_hours' => 1,
        'concern' => 'Unassigned oil',
        'status' => AppointmentStatus::Scheduled,
    ]);
    Appointment::query()->create([
        'customer_id' => $techCustomer->id,
        'created_by_user_id' => $advisor->id,
        'technician_user_id' => $technician->id,
        'starts_at' => ShopDisplayTimezone::parseLocal('2026-06-10 10:00')->utc(),
        'ends_at' => ShopDisplayTimezone::parseLocal('2026-06-10 11:00')->utc(),
        'estimated_labor_hours' => 1,
        'concern' => 'Edward brakes',
        'status' => AppointmentStatus::Scheduled,
    ]);
    Appointment::query()->create([
        'customer_id' => $bayCustomer->id,
        'created_by_user_id' => $advisor->id,
        'workstation_id' => $bay->id,
        'starts_at' => ShopDisplayTimezone::parseLocal('2026-06-10 11:00')->utc(),
        'ends_at' => ShopDisplayTimezone::parseLocal('2026-06-10 12:00')->utc(),
        'estimated_labor_hours' => 1,
        'concern' => 'Bay alignment',
        'status' => AppointmentStatus::Scheduled,
    ]);

    $agenda = app(SchedulingWorkspaceProjection::class)->resolve(
        Carbon::parse('2026-06-10'),
        'day',
        'agenda',
        null,
        false,
        DayLens::agenda(),
    );

    expect($agenda['agenda_count'])->toBe(3)
        ->and($agenda['total_count'])->toBe(3)
        ->and(collect($agenda['chips'])->pluck('key')->all())->toBe([
            'agenda',
            'unassigned',
            'technician:'.$technician->id,
            'workstation:'.$bay->id,
        ])
        ->and(collect($agenda['chips'])->firstWhere('key', 'agenda')['count'])->toBe(3)
        ->and(collect($agenda['chips'])->firstWhere('key', 'unassigned')['count'])->toBe(1);

    $edward = app(SchedulingWorkspaceProjection::class)->resolve(
        Carbon::parse('2026-06-10'),
        'day',
        'agenda',
        null,
        false,
        DayLens::technician((int) $technician->id),
    );

    expect($edward['lens'])->toBe('technician:'.$technician->id)
        ->and($edward['total_count'])->toBe(1)
        ->and($edward['agenda_count'])->toBe(3)
        ->and($edward['lane_rows'][0]['cards'][0]['concern'])->toBe('Edward brakes')
        ->and(collect($edward['chips'])->firstWhere('selected', true)['label'])->toBe('Edward Chen');

    $invalid = app(SchedulingWorkspaceProjection::class)->resolve(
        Carbon::parse('2026-06-10'),
        'day',
        'agenda',
        null,
        false,
        'technician:999999',
    );

    expect($invalid['lens'])->toBe('agenda')
        ->and($invalid['total_count'])->toBe(3);

    $this->actingAs($advisor)
        ->get(route('operations.appointments.index', [
            'day' => '2026-06-10',
            'view' => 'day',
            'lens' => 'technician:'.$technician->id,
        ]))
        ->assertOk()
        ->assertSee('ops-day-lens', false)
        ->assertSee('Edward Chen', false)
        ->assertSee('Edward brakes', false)
        ->assertDontSee('Unassigned oil', false)
        ->assertDontSee('Bay alignment', false);

    expect(collect($agenda['chips'])->contains(fn (array $chip): bool => $chip['key'] === 'unassigned'))->toBeTrue();

    Appointment::query()->update([
        'technician_user_id' => null,
        'workstation_id' => null,
    ]);

    $allUnassigned = app(SchedulingWorkspaceProjection::class)->resolve(
        Carbon::parse('2026-06-10'),
        'day',
        'agenda',
        null,
        false,
        DayLens::agenda(),
    );

    expect(collect($allUnassigned['chips'])->pluck('key')->all())->toBe(['agenda', 'unassigned'])
        ->and($allUnassigned['agenda_count'])->toBe(3)
        ->and(collect($allUnassigned['chips'])->firstWhere('key', 'unassigned')['count'])->toBe(3);

    Carbon::setTestNow();
});

test('schedule board defaults to day and month shows the following week', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-21 08:00:00', 'America/Denver'));
    $advisor = actingAsLearnCurrentAdvisor();

    $thisWeek = Customer::query()->create([
        'first_name' => 'Hunter',
        'last_name' => 'Owens',
        'phone' => '555-1021',
    ]);
    $nextWeek = Customer::query()->create([
        'first_name' => 'Alexis',
        'last_name' => 'Aldana',
        'phone' => '555-1024',
    ]);

    Appointment::query()->create([
        'customer_id' => $thisWeek->id,
        'created_by_user_id' => $advisor->id,
        'starts_at' => ShopDisplayTimezone::parseLocal('2026-08-21 13:00')->utc(),
        'ends_at' => ShopDisplayTimezone::parseLocal('2026-08-21 14:00')->utc(),
        'concern' => 'Smoke Test',
        'status' => AppointmentStatus::Confirmed,
    ]);
    Appointment::query()->create([
        'customer_id' => $nextWeek->id,
        'created_by_user_id' => $advisor->id,
        'starts_at' => ShopDisplayTimezone::parseLocal('2026-08-24 09:00')->utc(),
        'ends_at' => ShopDisplayTimezone::parseLocal('2026-08-24 10:00')->utc(),
        'concern' => 'Oil change',
        'status' => AppointmentStatus::Scheduled,
    ]);

    $week = app(SchedulingWorkspaceProjection::class)->resolve(Carbon::parse('2026-08-21'), 'week');
    $month = app(SchedulingWorkspaceProjection::class)->resolve(Carbon::parse('2026-08-21'), 'month');

    expect($week['view'])->toBe('week')
        ->and($week['week_days'])->toHaveCount(7)
        ->and($week['nav_next_date'])->toBe('2026-08-28')
        ->and(collect($week['week_days'])->firstWhere('date', '2026-08-21')['count'])->toBe(1)
        ->and(collect($week['week_days'])->firstWhere('date', '2026-08-24'))->toBeNull()
        ->and($month['view'])->toBe('month')
        ->and($month['month_weeks'])->not->toBeEmpty()
        ->and(collect($month['month_weeks'])->flatMap(fn (array $row) => $row['days'])->firstWhere('date', '2026-08-24')['count'])->toBe(1);

    $this->actingAs($advisor)
        ->get(route('operations.appointments.index', ['day' => '2026-08-21']))
        ->assertOk()
        ->assertSee('ops-cal-day', false)
        ->assertSee('Hunter Owens', false)
        ->assertDontSee('Alexis Aldana', false);

    $this->actingAs($advisor)
        ->get(route('operations.appointments.index', ['day' => '2026-08-21', 'view' => 'week']))
        ->assertOk()
        ->assertSee('ops-cal-week--board', false)
        ->assertSee('Hunter Owens', false)
        ->assertDontSee('Alexis Aldana', false);

    $this->actingAs($advisor)
        ->get(route('operations.appointments.index', ['day' => '2026-08-21', 'view' => 'month']))
        ->assertOk()
        ->assertSee('ops-cal-month', false)
        ->assertSee('Alexis Aldana', false);

    $this->actingAs($advisor)
        ->post(route('operations.appointments.board-view'), [
            'view' => 'month',
            'day' => '2026-08-21',
        ])
        ->assertRedirect(route('operations.appointments.index', [
            'day' => '2026-08-21',
            'view' => 'month',
        ]));

    expect($advisor->fresh()->schedule_board_view)->toBe('month');

    $this->actingAs($advisor)
        ->get(route('operations.appointments.index', ['day' => '2026-08-21']))
        ->assertOk()
        ->assertSee('ops-cal-month', false)
        ->assertSee('Alexis Aldana', false);

    Carbon::setTestNow();
});
