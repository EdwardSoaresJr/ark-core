<?php

use App\Ark\Operations\Appointments\Appointment;
use App\Ark\Operations\Appointments\AppointmentStatus;
use App\Ark\Operations\Appointments\SchedulingHours;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Workstations\Workstation;
use App\Ark\Runtime\Authorization\ArkRole;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\LivingDemoSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    ShopSettings::current()->update([
        'appointments_enabled' => true,
        'shop_timezone' => 'America/Denver',
        'scheduling_hours' => SchedulingHours::defaultWeekly(),
        'appointment_capacity_basis' => 'limiting_resource',
        'appointment_scheduling_target_percent' => 100,
        'appointment_capacity_enforcement' => 'warn',
    ]);
    ShopSettings::forgetCurrent();
    $this->seed(ArkAuthorizationSeeder::class);
});

test('overlapping technician appointments warn but save', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-10 08:00:00'));
    $advisor = actingAsLearnCurrentAdvisor();
    $technician = actingAsLearnCurrentStaff(ArkRole::Technician);
    $settings = ShopSettings::current();

    $bayA = Workstation::query()->create([
        'shop_settings_id' => $settings->id,
        'name' => 'Bay A',
        'is_active' => true,
        'accepts_scheduled_work' => true,
    ]);
    $bayB = Workstation::query()->create([
        'shop_settings_id' => $settings->id,
        'name' => 'Bay B',
        'is_active' => true,
        'accepts_scheduled_work' => true,
    ]);

    $customerA = Customer::query()->create([
        'first_name' => 'Ava',
        'last_name' => 'Lane',
        'phone' => '555-0301',
    ]);
    $customerB = Customer::query()->create([
        'first_name' => 'Ben',
        'last_name' => 'Reed',
        'phone' => '555-0302',
    ]);

    Appointment::query()->create([
        'customer_id' => $customerA->id,
        'technician_user_id' => $technician->id,
        'workstation_id' => $bayA->id,
        'created_by_user_id' => $advisor->id,
        'starts_at' => ShopDisplayTimezone::parseLocal('2026-06-10 10:00')->utc(),
        'ends_at' => ShopDisplayTimezone::parseLocal('2026-06-10 11:00')->utc(),
        'concern' => 'First job',
        'status' => AppointmentStatus::Scheduled,
        'estimated_labor_hours' => 1,
    ]);

    $this->actingAs($advisor)
        ->post(route('operations.appointments.store'), [
            'customer_id' => $customerB->id,
            'technician_user_id' => $technician->id,
            'workstation_id' => $bayB->id,
            'starts_at' => '2026-06-10T10:30',
            'ends_at' => '2026-06-10T11:30',
            'concern' => 'Overlap',
            'estimated_labor_hours' => 1,
        ])
        ->assertRedirect()
        ->assertSessionHas('schedule_warnings');

    Carbon::setTestNow();
});

test('overlapping bay appointments warn but save', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-10 08:00:00'));
    $advisor = actingAsLearnCurrentAdvisor();
    $settings = ShopSettings::current();

    $bay = Workstation::query()->create([
        'shop_settings_id' => $settings->id,
        'name' => 'Bay 1',
        'is_active' => true,
        'accepts_scheduled_work' => true,
    ]);

    $customerA = Customer::query()->create([
        'first_name' => 'Cara',
        'last_name' => 'Moss',
        'phone' => '555-0303',
    ]);
    $customerB = Customer::query()->create([
        'first_name' => 'Drew',
        'last_name' => 'Peck',
        'phone' => '555-0304',
    ]);

    Appointment::query()->create([
        'customer_id' => $customerA->id,
        'workstation_id' => $bay->id,
        'created_by_user_id' => $advisor->id,
        'starts_at' => ShopDisplayTimezone::parseLocal('2026-06-10 10:00')->utc(),
        'ends_at' => ShopDisplayTimezone::parseLocal('2026-06-10 11:00')->utc(),
        'concern' => 'Bay job',
        'status' => AppointmentStatus::Confirmed,
        'estimated_labor_hours' => 1,
    ]);

    $this->actingAs($advisor)
        ->post(route('operations.appointments.store'), [
            'customer_id' => $customerB->id,
            'workstation_id' => $bay->id,
            'starts_at' => '2026-06-10T10:15',
            'ends_at' => '2026-06-10T11:15',
            'concern' => 'Bay overlap',
            'estimated_labor_hours' => 1,
        ])
        ->assertRedirect()
        ->assertSessionHas('schedule_warnings');

    Carbon::setTestNow();
});

test('appointments outside shop hours are rejected', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-10 08:00:00'));
    $advisor = actingAsLearnCurrentAdvisor();
    $customer = Customer::query()->create([
        'first_name' => 'Eve',
        'last_name' => 'Quill',
        'phone' => '555-0305',
    ]);

    $this->actingAs($advisor)
        ->post(route('operations.appointments.store'), [
            'customer_id' => $customer->id,
            'starts_at' => '2026-06-10T18:00',
            'ends_at' => '2026-06-10T19:00',
            'concern' => 'After hours',
            'estimated_labor_hours' => 1,
        ])
        ->assertSessionHasErrors('starts_at');

    Carbon::setTestNow();
});

test('living demo seeder creates a busy tuesday schedule', function () {
    $this->seed(LivingDemoSeeder::class);

    expect(Appointment::query()->where('notes', 'like', 'Living Demo%')->count())->toBe(5)
        ->and(Workstation::query()->where('accepts_scheduled_work', true)->count())->toBeGreaterThanOrEqual(2)
        ->and(ShopSettings::current()->appointments_enabled)->toBeTrue();
});
